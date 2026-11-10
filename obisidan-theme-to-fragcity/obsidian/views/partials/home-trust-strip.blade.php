{{-- resources/views/partials/home-trust-strip.blade.php --}}
@php
    $enabled = (bool) theme('trust_strip_enabled', true);
    if (!$enabled) return;

    $ratingRaw  = trim((string) theme('trust_strip_rating', '4.5'));
    $reviewsRaw = trim((string) theme('trust_strip_reviews', '101'));
    $sourceName = trim((string) theme('trust_strip_source', 'Trustpilot'));
    $href       = trim((string) theme('trust_strip_href', ''));

    $rating = is_numeric($ratingRaw) ? (float) $ratingRaw : 4.5;
    $rating = max(0, min(5, $rating));

    $reviews = is_numeric($reviewsRaw) ? (int) $reviewsRaw : 101;
    if ($reviews < 0) $reviews = 0;

    // fraction for each star: 0.0, 0.5, 1.0 etc
    $starFill = function (int $i) use ($rating) {
        $v = $rating - ($i - 1);
        if ($v <= 0) return 0.0;
        if ($v >= 1) return 1.0;
        return $v;
    };

    $WrapperTag = $href !== '' ? 'a' : 'div';
@endphp

<section class="relative py-10 md:py-12">
    <div class="container">
        <{{ $WrapperTag }}
            @if($href !== '') href="{{ $href }}" target="_blank" rel="noopener noreferrer" @endif
            class="mx-auto flex max-w-[980px] items-center justify-center rounded-2xl border border-neutral/25 bg-background-secondary/25 px-6 py-5 backdrop-blur transition hover:bg-background-secondary/35"
        >
            <div class="flex flex-col items-center gap-3 sm:flex-row sm:gap-6">
                {{-- Stars --}}
                <div class="flex items-center gap-1.5" aria-label="Rating: {{ number_format($rating, 1) }} out of 5">
                    @for ($i = 1; $i <= 5; $i++)
                        @php
                            // snap to clean halves (0, 50, 100) to avoid weird fractional widths
                            $pct = (int) (round($starFill($i) * 2) / 2 * 100);
                        @endphp

                        <span class="relative inline-block h-5 w-5" aria-hidden="true">
                            {{-- empty --}}
                            <svg viewBox="0 0 24 24" class="absolute inset-0 h-5 w-5" fill="rgba(0,182,122,.20)">
                                <path d="M12 2.2l2.9 6.1 6.7.9-4.9 4.7 1.2 6.6L12 17.9 6.1 20.5l1.2-6.6L2.4 9.2l6.7-.9L12 2.2z"/>
                            </svg>

                            {{-- fill (supports half star cleanly) --}}
                            <span class="absolute inset-0 overflow-hidden" style="width: {{ $pct }}%;">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="#00b67a">
                                    <path d="M12 2.2l2.9 6.1 6.7.9-4.9 4.7 1.2 6.6L12 17.9 6.1 20.5l1.2-6.6L2.4 9.2l6.7-.9L12 2.2z"/>
                                </svg>
                            </span>
                        </span>
                    @endfor
                </div>

                {{-- Text (NO unicode separator char) --}}
                <div class="flex items-center gap-3 text-sm font-semibold text-base">
                    <span class="tabular-nums">{{ number_format($rating, 1) }}</span>
                    <span class="text-base/40">|</span>
                    <span class="tabular-nums">{{ number_format($reviews) }}</span>
                    <span class="text-base/70 font-medium">reviews on</span>
                    <span class="font-semibold">{{ $sourceName !== '' ? $sourceName : 'Trustpilot' }}</span>
                </div>
            </div>
        </{{ $WrapperTag }}>
    </div>
</section>
