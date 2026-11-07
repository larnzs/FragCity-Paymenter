<?php

namespace Paymenter\Extensions\Others\FragCitySupportAPI\Services;

use App\Models\Service;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\TicketMessageAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SupportTicketService
{
    public function index(array $payload, User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        $validator = Validator::make($payload, [
            'status' => ['nullable', 'string', 'max:32'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        $perPage = (int) ($data['per_page'] ?? 15);

        $query = Ticket::query()
            ->where('user_id', $user->id)
            ->withCount('messages')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        $tickets = $query->paginate($perPage, ['*'], 'page', (int) ($data['page'] ?? 1));

        return response()->json([
            'success' => true,
            'data' => array_map(fn (Ticket $ticket) => $this->ticketSummary($ticket), $tickets->items()),
            'pagination' => [
                'current_page' => $tickets->currentPage(),
                'per_page' => $tickets->perPage(),
                'last_page' => $tickets->lastPage(),
                'total' => $tickets->total(),
            ],
        ]);
    }

    public function show(array $payload, User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        $validator = Validator::make($payload, [
            'ticket_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $ticket = Ticket::query()
            ->where('user_id', $user->id)
            ->find((int) $validator->validated()['ticket_id']);

        if (!$ticket) {
            return $this->notFound('ticket_not_found');
        }

        $messages = $ticket->messages()
            ->with('attachments')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                ...$this->ticketSummary($ticket),
                'messages' => $messages->map(fn (TicketMessage $message) => $this->messageResource($message, $user))->values(),
            ],
        ]);
    }

    public function create(array $payload, User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        $departments = array_values((array) config('settings.ticket_departments', []));
        $departmentRules = $departments
            ? ['required', 'string', Rule::in($departments)]
            : ['nullable', 'string', 'max:100'];

        $validator = Validator::make($payload, [
            'subject' => ['required', 'string', 'min:3', 'max:191'],
            'department' => $departmentRules,
            'service_id' => ['nullable', 'integer', 'min:1'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'message' => ['required', 'string', 'max:20000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*.filename' => ['required', 'string', 'max:255'],
            'attachments.*.content_base64' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        if (trim($data['message']) === '') {
            return $this->error('message_required', 422);
        }

        if (!empty($data['service_id']) && !Service::query()
            ->where('id', (int) $data['service_id'])
            ->where('user_id', $user->id)
            ->exists()) {
            return $this->notFound('service_not_found');
        }

        $rateLimitKey = 'fragcity_support_ticket_create:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return $this->error('ticket_create_rate_limited', 429);
        }
        RateLimiter::hit($rateLimitKey, 30);

        $files = $this->normalizeAttachments($data['attachments'] ?? []);
        if ($files instanceof JsonResponse) {
            return $files;
        }

        $storedPaths = [];

        try {
            $ticket = DB::transaction(function () use ($data, $user, $files, &$storedPaths): Ticket {
                $ticket = Ticket::create([
                    'user_id' => $user->id,
                    'subject' => trim($data['subject']),
                    'department' => isset($data['department']) ? trim((string) $data['department']) : null,
                    'service_id' => $data['service_id'] ?? null,
                    'priority' => $data['priority'],
                    'status' => 'open',
                ]);

                $message = $ticket->messages()->create([
                    'user_id' => $user->id,
                    'message' => $data['message'],
                ]);

                $this->persistAttachments($message, $files, $storedPaths);

                return $ticket;
            }, 3);
        } catch (Throwable $exception) {
            $this->removeStoredFiles($storedPaths);
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => $this->ticketSummary($ticket),
        ], 201);
    }

    public function reply(array $payload, User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        $validator = Validator::make($payload, [
            'ticket_id' => ['required', 'integer', 'min:1'],
            'message' => ['required', 'string', 'max:20000'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*.filename' => ['required', 'string', 'max:255'],
            'attachments.*.content_base64' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $data = $validator->validated();
        if (trim($data['message']) === '') {
            return $this->error('message_required', 422);
        }

        $ticket = Ticket::query()
            ->where('user_id', $user->id)
            ->find((int) $data['ticket_id']);

        if (!$ticket) {
            return $this->notFound('ticket_not_found');
        }

        if ($ticket->status === 'closed') {
            return $this->error('ticket_closed_reopen_before_reply', 409);
        }

        $rateLimitKey = 'fragcity_support_ticket_reply:' . $ticket->id . ':' . $user->id;
        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return $this->error('ticket_reply_rate_limited', 429);
        }
        RateLimiter::hit($rateLimitKey, 10);

        $files = $this->normalizeAttachments($data['attachments'] ?? []);
        if ($files instanceof JsonResponse) {
            return $files;
        }

        $storedPaths = [];

        try {
            $message = DB::transaction(function () use ($data, $ticket, $user, $files, &$storedPaths): TicketMessage {
                $message = $ticket->messages()->create([
                    'user_id' => $user->id,
                    'message' => $data['message'],
                ]);

                $this->persistAttachments($message, $files, $storedPaths);
                $ticket->touch();

                return $message;
            }, 3);
        } catch (Throwable $exception) {
            $this->removeStoredFiles($storedPaths);
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'data' => $this->messageResource($message->load('attachments'), $user),
        ], 201);
    }

    public function close(array $payload, User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        if (config('settings.ticket_client_closing_disabled', false)) {
            return $this->error('ticket_client_closing_disabled', 403);
        }

        return $this->changeStatus($payload, $user, 'closed');
    }

    public function reopen(array $payload, User $user): JsonResponse
    {
        if ($disabled = $this->ticketsDisabled()) {
            return $disabled;
        }

        return $this->changeStatus($payload, $user, 'open');
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
            ->whereHas('ticketMessage.ticket', fn ($query) => $query->where('user_id', $user->id))
            ->first();

        if (!$attachment || !is_file($attachment->local_path)) {
            return $this->notFound('attachment_not_found');
        }

        return response()->download(
            $attachment->local_path,
            $attachment->filename,
            ['Content-Type' => $attachment->mime_type]
        );
    }

    private function changeStatus(array $payload, User $user, string $status): JsonResponse
    {
        $validator = Validator::make($payload, [
            'ticket_id' => ['required', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $ticket = Ticket::query()
            ->where('user_id', $user->id)
            ->find((int) $validator->validated()['ticket_id']);

        if (!$ticket) {
            return $this->notFound('ticket_not_found');
        }

        $ticket->update(['status' => $status]);

        return response()->json([
            'success' => true,
            'data' => $this->ticketSummary($ticket->fresh()),
        ]);
    }

    private function normalizeAttachments(array $attachments): array|JsonResponse
    {
        $normalized = [];
        $totalBytes = 0;
        $maxFileBytes = 10 * 1024 * 1024;
        $maxTotalBytes = 20 * 1024 * 1024;

        foreach ($attachments as $attachment) {
            $filename = str_replace('\\', '/', (string) ($attachment['filename'] ?? ''));
            $filename = basename($filename);

            if (
                $filename === ''
                || $filename === '.'
                || $filename === '..'
                || preg_match('/[\x00-\x1F\x7F]/', $filename)
                || !is_string($attachment['content_base64'] ?? null)
            ) {
                return $this->error('invalid_attachment', 422);
            }

            $contents = base64_decode($attachment['content_base64'], true);
            if ($contents === false || $contents === '') {
                return $this->error('invalid_attachment_encoding', 422);
            }

            $size = strlen($contents);
            $totalBytes += $size;

            if ($size > $maxFileBytes || $totalBytes > $maxTotalBytes) {
                return $this->error('attachment_size_limit_exceeded', 413);
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($contents) ?: 'application/octet-stream';

            $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));
            if (!preg_match('/^[a-z0-9]{1,10}$/D', $extension)) {
                $extension = '';
            }

            $normalized[] = [
                'filename' => $filename,
                'contents' => $contents,
                'mime_type' => $mimeType,
                'filesize' => $size,
                'extension' => $extension,
            ];
        }

        return $normalized;
    }

    private function persistAttachments(TicketMessage $message, array $files, array &$storedPaths): void
    {
        foreach ($files as $file) {
            $filename = (string) Str::ulid() . ($file['extension'] !== '' ? '.' . $file['extension'] : '');
            $path = 'tickets/uploads/' . $filename;

            if (!Storage::disk('local')->put($path, $file['contents'])) {
                throw new RuntimeException('ticket_attachment_storage_failed');
            }

            $storedPaths[] = $path;

            $message->attachments()->create([
                'path' => $path,
                'filename' => $file['filename'],
                'filesize' => $file['filesize'],
                'mime_type' => $file['mime_type'],
            ]);
        }
    }

    private function removeStoredFiles(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    private function ticketSummary(Ticket $ticket): array
    {
        return [
            'id' => (int) $ticket->id,
            'subject' => (string) $ticket->subject,
            'status' => (string) $ticket->status,
            'priority' => (string) $ticket->priority,
            'department' => $ticket->department,
            'service_id' => $ticket->service_id ? (int) $ticket->service_id : null,
            'message_count' => isset($ticket->messages_count) ? (int) $ticket->messages_count : (int) $ticket->messages()->count(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'updated_at' => $ticket->updated_at?->toIso8601String(),
        ];
    }

    private function messageResource(TicketMessage $message, User $customer): array
    {
        return [
            'id' => (int) $message->id,
            'sender' => (int) $message->user_id === (int) $customer->id ? 'customer' : 'staff',
            'message' => (string) $message->message,
            'created_at' => $message->created_at?->toIso8601String(),
            'attachments' => $message->attachments->map(fn (TicketMessageAttachment $attachment) => [
                'uuid' => (string) $attachment->uuid,
                'filename' => (string) $attachment->filename,
                'filesize' => (int) $attachment->filesize,
                'mime_type' => (string) $attachment->mime_type,
                'download_endpoint' => '/api/v1/fragcity/support/attachments/download',
            ])->values(),
        ];
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

    private function notFound(string $error): JsonResponse
    {
        return $this->error($error, 404);
    }

    private function error(string $error, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status);
    }
}
