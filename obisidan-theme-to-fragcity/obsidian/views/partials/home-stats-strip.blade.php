{{-- resources/views/partials/home-stats-strip.blade.php --}}
@php
    $enabled = (bool) theme('stats_strip_enabled', true);
    if (!$enabled) return;

    $title    = trim((string) theme('stats_strip_title', ''));
    $subtitle = trim((string) theme('stats_strip_subtitle', ''));

    // MATCHES theme.php keys EXACTLY:
    // stats_strip_1_value, stats_strip_1_label ... stats_strip_4_value, stats_strip_4_label
    $items = [];
    for ($i = 1; $i <= 4; $i++) {
        $val = trim((string) theme("stats_strip_{$i}_value", ''));
        $lab = trim((string) theme("stats_strip_{$i}_label", ''));

        // Only include if at least something is set
        if ($val !== '' || $lab !== '') {
            $items[] = [
                'value' => $val !== '' ? $val : '',
                'label' => $lab !== '' ? $lab : '',
            ];
        }
    }

    // Hard fallback if nothing set
    if (count($items) === 0) {
        $items = [
            ['value' => '99.99%', 'label' => 'Uptime'],
            ['value' => '<50ms',  'label' => 'Latency'],
            ['value' => '24/7',   'label' => 'Human Support'],
            ['value' => 'Instant','label' => 'Deploy'],
        ];
    }
@endphp

<section class="relative py-10 md:py-12">
    <div class="container">
        <div class="mx-auto max-w-[1100px]">
            @if($title !== '' || $subtitle !== '')
                <div class="mb-6 text-center">
                    @if($title !== '')
                        <h3 class="text-lg font-semibold tracking-tight text-base">{{ $title }}</h3>
                    @endif
                    @if($subtitle !== '')
                        <p class="mt-1 text-sm text-base/60">{{ $subtitle }}</p>
                    @endif
                </div>
            @endif

            {{-- ONE LINE: no wrapping. On small screens it scrolls horizontally. --}}
            <div class="rounded-2xl bg-background-secondary/10 px-4 py-4 backdrop-blur">
                <div class="flex flex-nowrap items-stretch justify-between gap-4 overflow-x-auto whitespace-nowrap">
                    @foreach($items as $item)
                        <div class="min-w-[220px] flex-1 rounded-xl bg-black/25 px-6 py-4 text-center transition hover:bg-black/35">
                            <div class="text-lg font-semibold tabular-nums text-base">
                                {{ $item['value'] }}
                            </div>
                            <div class="mt-1 text-xs font-medium text-base/65">
                                {{ $item['label'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</section>
