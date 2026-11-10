{{-- themes/obsidian/views/tickets/widget.blade.php --}}

@php
    $statusMeta = [
        'open' => [
            'label' => 'Open',
            'pill'  => 'bg-emerald-500/15 text-emerald-200 border-emerald-500/25',
        ],
        'replied' => [
            'label' => 'Answered',
            'pill'  => 'bg-sky-500/15 text-sky-200 border-sky-500/25',
        ],
        'closed' => [
            'label' => 'Closed',
            'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
        ],
    ];
@endphp

<div class="space-y-4">
    @forelse ($tickets as $ticket)
        @php
            $meta = $statusMeta[$ticket->status] ?? [
                'label' => ucfirst($ticket->status),
                'pill'  => 'bg-neutral-500/15 text-base/70 border-neutral-500/25',
            ];

            $lastMessage = $ticket->messages()
                ->orderBy('created_at', 'desc')
                ->first();

            $lastUpdated = $lastMessage
                ? $lastMessage->created_at->format('D, F j, Y - H:i')
                : null;

            $department = $ticket->department ? (string) $ticket->department : null;
            $messageCount = $ticket->messages()->count();
        @endphp

        <a href="{{ route('tickets.show', $ticket) }}" wire:navigate class="group block">
            <div class="rounded-3xl border border-neutral bg-background-secondary/15 transition hover:bg-background-secondary/20">
                <div class="p-6 min-h-[130px] flex flex-col justify-between">

                    {{-- Top --}}
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-base truncate">
                                {{ $ticket->subject }}
                            </h3>

                            <p class="mt-1 text-xs text-base/60">
                                @if ($lastUpdated)
                                    Updated {{ $lastUpdated }}
                                @else
                                    No updates yet
                                @endif

                                @if ($department)
                                    <span class="text-base/40"> - </span>{{ $department }}
                                @endif
                            </p>
                        </div>

                        <span class="inline-flex items-center rounded-full px-3 py-1 text-[11px] font-semibold border {{ $meta['pill'] }}">
                            {{ $meta['label'] }}
                        </span>
                    </div>

                    {{-- Bottom --}}
                    <div class="mt-4 pt-4 border-t border-neutral flex items-center justify-between">
                        <div class="text-xs text-base/60">
                            Messages
                        </div>

                        <div class="text-2xl font-semibold text-base">
                            {{ $messageCount }}
                        </div>
                    </div>

                </div>
            </div>
        </a>
    @empty
        <div class="rounded-3xl border border-neutral bg-background-secondary/15 p-8 text-center text-sm text-base/60">
            No tickets yet.
        </div>
    @endforelse
</div>
