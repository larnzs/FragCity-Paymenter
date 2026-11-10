<section id="pricing" class="relative py-32">
    <div class="container">
        @php
            $pricingCtaUrl = $primaryCategoryUrl ?? route('home');

            $pricingEnabled = (bool) theme('pricing_section_enabled', true);
            $fullscreenPricing = (bool) theme('pricing_fullscreen_images', false);

            $pricingTitle = trim((string) theme('pricing_title', 'Transparent Pricing'));
            $pricingSubtitle = trim((string) theme('pricing_subtitle', 'Simple monthly rates. No hidden fees. Cancel anytime.'));
            $pricingFooter = trim((string) theme('pricing_footer_text', 'All plans include money-back guarantee. Pay monthly or annually.'));

            $defaultFeatures1 = "2 vCPU cores\n4 GB RAM\n80 GB NVMe storage\n2 TB bandwidth\nDDoS protection\nDaily backups";
            $defaultFeatures2 = "4 vCPU cores\n16 GB RAM\n200 GB NVMe storage\n6 TB bandwidth\nDDoS protection\nHourly backups\nPriority support\nCustom ISO support";
            $defaultFeatures3 = "8 vCPU cores\n32 GB RAM\n500 GB NVMe storage\nUnlimited bandwidth\nAdvanced DDoS protection\nReal-time backups\nDedicated support\nCustom configurations\nSLA guarantee";

            $cards = collect([
                [
                    'enabled' => (bool) theme('pricing_card_1_enabled', true),
                    'highlighted' => false,
                    'title' => trim((string) theme('pricing_card_1_title', 'Starter')),
                    'subtitle' => trim((string) theme('pricing_card_1_subtitle', 'For small projects and testing')),
                    'price' => trim((string) theme('pricing_card_1_price', '12')),
                    'suffix' => trim((string) theme('pricing_card_1_price_suffix', '/month')),
                    'features_raw' => theme('pricing_card_1_features', $defaultFeatures1),
                    'image_path' => trim((string) theme('pricing_card_1_image', '')),
                    'button_text' => trim((string) theme('pricing_card_1_button_text', 'Get Started')),
                    'button_href' => trim((string) theme('pricing_card_1_button_href', '')),
                ],
                [
                    'enabled' => (bool) theme('pricing_card_2_enabled', true),
                    'highlighted' => (bool) theme('pricing_card_2_highlighted', true),
                    'title' => trim((string) theme('pricing_card_2_title', 'Professional')),
                    'subtitle' => trim((string) theme('pricing_card_2_subtitle', 'For production workloads')),
                    'price' => trim((string) theme('pricing_card_2_price', '34')),
                    'suffix' => trim((string) theme('pricing_card_2_price_suffix', '/month')),
                    'features_raw' => theme('pricing_card_2_features', $defaultFeatures2),
                    'image_path' => trim((string) theme('pricing_card_2_image', '')),
                    'button_text' => trim((string) theme('pricing_card_2_button_text', 'Get Started')),
                    'button_href' => trim((string) theme('pricing_card_2_button_href', '')),
                ],
                [
                    'enabled' => (bool) theme('pricing_card_3_enabled', true),
                    'highlighted' => false,
                    'title' => trim((string) theme('pricing_card_3_title', 'Enterprise')),
                    'subtitle' => trim((string) theme('pricing_card_3_subtitle', 'For demanding applications')),
                    'price' => trim((string) theme('pricing_card_3_price', '89')),
                    'suffix' => trim((string) theme('pricing_card_3_price_suffix', '/month')),
                    'features_raw' => theme('pricing_card_3_features', $defaultFeatures3),
                    'image_path' => trim((string) theme('pricing_card_3_image', '')),
                    'button_text' => trim((string) theme('pricing_card_3_button_text', 'Get Started')),
                    'button_href' => trim((string) theme('pricing_card_3_button_href', '')),
                ],
            ])->filter(fn ($c) => $c['enabled'])->values();

            $skipPricing = (!$pricingEnabled || $cards->count() === 0);
        @endphp

        @if(!$skipPricing)
            <div class="mx-auto mb-24 max-w-[600px] text-center">
                <h2 class="mb-6 text-4xl tracking-tight sm:text-5xl">
                    {{ $pricingTitle }}
                </h2>
                <p class="text-lg text-base/60">
                    {{ $pricingSubtitle }}
                </p>
            </div>

            <div class="mx-auto grid max-w-[1200px] gap-6 lg:grid-cols-3">
                @foreach($cards as $card)
                    @php
                        $imgUrl = $card['image_path'] !== '' ? Storage::url($card['image_path']) : '';
                        $href = $card['button_href'] !== '' ? $card['button_href'] : $pricingCtaUrl;

                        $raw = $card['features_raw'] ?? '';

                        if (is_array($raw)) {
                            $raw = implode("\n", $raw);
                        } elseif (is_object($raw) && method_exists($raw, '__toString')) {
                            $raw = (string) $raw;
                        } else {
                            $raw = (string) $raw;
                        }

                        $raw = trim($raw);

                        $features = collect(preg_split("/\r\n|\n|\r/", $raw))
                            ->map(fn ($l) => trim($l))
                            ->filter(fn ($l) => $l !== '')
                            ->values();

                        $isHighlighted = (bool) $card['highlighted'];
                    @endphp

                    @if($fullscreenPricing && $imgUrl !== '')
                        <div
                            class="relative flex h-full min-h-[520px] flex-col overflow-hidden rounded-2xl border border-neutral/50 bg-background-secondary p-10 transition
                                   {{ $isHighlighted ? 'lg:scale-[1.05]' : '' }}"
                            style="background-image:url('{{ $imgUrl }}'); background-size:cover; background-position:center;"
                        >
                            <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-black/35 via-black/45 to-black/75"></div>

                            <div class="relative z-10 flex h-full flex-col">
                                <div class="mb-8">
                                    <h3 class="mb-2 text-2xl font-semibold tracking-tight text-white">
                                        {{ $card['title'] }}
                                    </h3>
                                    @if($card['subtitle'] !== '')
                                        <p class="text-sm text-white/75">
                                            {{ $card['subtitle'] }}
                                        </p>
                                    @endif
                                </div>

                                <div class="mb-10">
                                    <div class="flex items-baseline gap-1 text-white">
                                        <span class="text-5xl tracking-tight">
                                            {{ $card['price'] !== '' ? $card['price'] : '—' }}
                                        </span>
                                        @if($card['suffix'] !== '')
                                            <span class="text-white/70">{{ $card['suffix'] }}</span>
                                        @endif
                                    </div>
                                </div>

                                @if($features->count() > 0)
                                    <ul class="mb-10 space-y-4">
                                        @foreach($features as $feature)
                                            <li class="flex items-start gap-3">
                                                <span class="mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-white/70"></span>
                                                <span class="text-white/80">{{ $feature }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="mt-auto pt-4">
                                    <a
                                        href="{{ $href }}"
                                        wire:navigate
                                        class="block rounded-full px-8 py-3.5 text-center font-semibold transition border border-white/25 bg-black/25 text-white backdrop-blur hover:bg-black/35"
                                    >
                                        {{ $card['button_text'] !== '' ? $card['button_text'] : 'Get Started' }}
                                    </a>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="relative flex h-full flex-col overflow-hidden rounded-2xl border border-neutral bg-background-secondary p-10 transition {{ $isHighlighted ? 'lg:scale-[1.05]' : '' }}">
                            @if($imgUrl !== '')
                                <div class="-mx-10 -mt-10 mb-8 overflow-hidden">
                                    <div class="aspect-[16/9] w-full bg-background-secondary/55">
                                        <img
                                            src="{{ $imgUrl }}"
                                            alt="{{ $card['title'] }}"
                                            class="h-full w-full object-cover"
                                            loading="lazy"
                                        >
                                    </div>
                                </div>
                            @endif

                            <div class="mb-8">
                                <h3 class="mb-2 text-2xl tracking-tight">
                                    {{ $card['title'] }}
                                </h3>
                                @if($card['subtitle'] !== '')
                                    <p class="text-sm text-base/50">
                                        {{ $card['subtitle'] }}
                                    </p>
                                @endif
                            </div>

                            <div class="mb-10">
                                <div class="flex items-baseline gap-1">
                                    <span class="text-5xl tracking-tight">
                                        {{ $card['price'] !== '' ? $card['price'] : '—' }}
                                    </span>
                                    @if($card['suffix'] !== '')
                                        <span class="text-base/50">{{ $card['suffix'] }}</span>
                                    @endif
                                </div>
                            </div>

                            @if($features->count() > 0)
                                <ul class="mb-10 space-y-4">
                                    @foreach($features as $feature)
                                        <li class="flex items-start gap-3">
                                            <span class="mt-2 h-1.5 w-1.5 flex-shrink-0 rounded-full bg-base/60"></span>
                                            <span class="text-base/70">{{ $feature }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-auto pt-4">
                                <a
                                    href="{{ $href }}"
                                    wire:navigate
                                    class="block rounded-full px-8 py-3.5 text-center transition
                                    {{ $isHighlighted
                                        ? 'bg-base text-background hover:bg-base/90'
                                        : 'border border-neutral bg-background hover:bg-background-secondary' }}"
                                >
                                    {{ $card['button_text'] !== '' ? $card['button_text'] : 'Get Started' }}
                                </a>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>

            @if($pricingFooter !== '')
                <div class="mt-16 text-center">
                    <p class="text-base/50">
                        {{ $pricingFooter }}
                    </p>
                </div>
            @endif
        @endif
    </div>
</section>
