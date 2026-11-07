<?php

namespace Paymenter\Extensions\Others\FragCitySupportAPI\Services;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SupportBridgeService
{
    public function options(User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'departments' => array_values((array) config('settings.ticket_departments', [])),
                'priorities' => ['low', 'medium', 'high'],
                'customer_can_close' => !config('settings.ticket_client_closing_disabled', false),
                'max_attachments_per_message' => 5,
                'max_attachment_bytes' => 10 * 1024 * 1024,
                'supported_attachment_types' => [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'image/gif',
                    'application/pdf',
                    'text/plain',
                    'text/csv',
                    'application/json',
                    'application/zip',
                    'application/x-zip-compressed',
                ],
            ],
        ]);
    }

    /**
     * Upload one attachment to an existing message authored by this customer.
     * The official Paymenter Admin API has no ticket-message upload endpoint.
     */
    public function upload(array $payload, User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        $validator = Validator::make($payload, [
            'ticket_id' => ['required', 'integer', 'min:1'],
            'message_id' => ['required', 'integer', 'min:1'],
            'filename' => ['required', 'string', 'min:1', 'max:255'],
            'content_base64' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();

        $message = TicketMessage::query()
            ->whereKey((int) $data['message_id'])
            ->where('ticket_id', (int) $data['ticket_id'])
            ->where('user_id', (int) $user->id)
            ->whereHas('ticket', function ($query) use ($user): void {
                $query->where('user_id', (int) $user->id)
                    ->where('status', '<>', 'closed');
            })
            ->with('ticket')
            ->first();

        if (!$message) {
            return $this->error('ticket_message_not_found_or_not_owned', 404);
        }

        if ($message->attachments()->count() >= 5) {
            return $this->error('message_attachment_limit_reached', 422);
        }

        $rateLimitKey = 'fragcity_support_attachment_upload:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            return $this->error('attachment_upload_rate_limited', 429);
        }

        $contents = base64_decode((string) $data['content_base64'], true);
        if ($contents === false || $contents === '') {
            return $this->error('invalid_attachment_encoding', 422);
        }

        $filesize = strlen($contents);
        if ($filesize > 10 * 1024 * 1024) {
            return $this->error('attachment_size_limit_exceeded', 413);
        }

        $originalFilename = str_replace('\\', '/', (string) $data['filename']);
        $originalFilename = basename($originalFilename);

        if (
            $originalFilename === ''
            || $originalFilename === '.'
            || $originalFilename === '..'
            || preg_match('/[\x00-\x1F\x7F]/', $originalFilename)
        ) {
            return $this->error('invalid_attachment_filename', 422);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/json' => 'json',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
        ];

        if (!isset($allowedTypes[$mimeType])) {
            return $this->error('attachment_type_not_allowed', 415);
        }

        RateLimiter::hit($rateLimitKey, 900);

        $storedFilename = (string) Str::ulid() . '.' . $allowedTypes[$mimeType];
        $path = 'tickets/uploads/' . $storedFilename;

        try {
            if (!Storage::disk('local')->put($path, $contents)) {
                throw new RuntimeException('ticket_attachment_storage_failed');
            }

            $attachment = $message->attachments()->create([
                'path' => $path,
                'filename' => $originalFilename,
                'filesize' => $filesize,
                'mime_type' => $mimeType,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'uuid' => (string) $attachment->uuid,
                'filename' => (string) $attachment->filename,
                'filesize' => (int) $attachment->filesize,
                'mime_type' => (string) $attachment->mime_type,
            ],
        ], 201);
    }

    public function download(array $payload, User $user): BinaryFileResponse|JsonResponse
    {
        $validator = Validator::make($payload, [
            'attachment_uuid' => ['required', 'uuid'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $attachment = TicketMessageAttachment::query()
            ->where('uuid', $validator->validated()['attachment_uuid'])
            ->whereHas('ticketMessage.ticket', function ($query) use ($user): void {
                $query->where('user_id', (int) $user->id);
            })
            ->first();

        if (!$attachment || !is_file($attachment->local_path)) {
            return $this->error('attachment_not_found', 404);
        }

        return response()->download(
            $attachment->local_path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    private function ticketsDisabled(): ?JsonResponse
    {
        if (config('settings.tickets_disabled', false)) {
            return $this->error('tickets_disabled', 503);
        }

        return null;
    }

    private function validationError($validator): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => 'validation_failed',
            'fields' => $validator->errors()->keys(),
        ], 422);
    }

    private function error(string $error, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status);
    }
}
