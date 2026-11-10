<div>
    @php
        $primaryCategoryUrl = route('home');

        // Primary CTA destination:
        // 1) If hero_primary_href is set, use it.
        // 2) Otherwise fall back to the first category (if any).
        $primaryHref = trim((string) theme('hero_primary_href', ''));
        if ($primaryHref !== '') {
            $primaryCategoryUrl = $primaryHref;
        } else {
            $firstCategory = $categories->first();
            if ($firstCategory) {
                $primaryCategoryUrl = route('category.show', ['category' => $firstCategory->slug]);
            }
        }

        $fullscreenCards = (bool) theme('infrastructure_fullscreen_images', false);
    @endphp

    <div class="flex flex-col">
        {{-- HERO --}}
        <section class="relative overflow-hidden pt-20 pb-20 md:pt-36 md:pb-24 lg:pt-40 lg:pb-20">
            <div class="container relative">
                <div class="mx-auto max-w-[920px] text-center">
                    <p class="text-xs font-semibold tracking-[0.18em] uppercase text-base/55">
                        {{ theme('hero_kicker', 'Premium hosting built for performance') }}
                    </p>

                    <h1 class="mt-7 text-4xl font-semibold tracking-tight leading-[1.08] sm:text-5xl lg:text-6xl">
                        {{ theme('hero_title', 'Infrastructure') }}<br>
                        {{ theme('hero_title_line_2', 'Forged in Precision') }}
                    </h1>

                    <p class="mx-auto mt-9 max-w-[680px] text-base text-base/60 sm:text-lg">
                        {{ theme('hero_subtitle', 'Performance hosting for developers, gamers, and businesses who demand reliability with zero compromise.') }}
                    </p>

                    <div class="mt-12 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        <a
                            href="{{ $primaryCategoryUrl }}"
                            class="inline-flex items-center justify-center rounded-full bg-base px-12 py-4 text-sm font-semibold text-background transition hover:bg-base/90 focus:outline-none focus:ring-0"
                        >
                            {{ theme('hero_primary_text', 'Deploy Now') }}
                        </a>

                        <a
                            href="{{ theme('hero_secondary_href', '#services') }}"
                            class="inline-flex items-center justify-center rounded-full border border-neutral/40 bg-background-secondary/40 px-12 py-4 text-sm font-semibold text-base transition hover:bg-background-secondary/65 focus:outline-none focus:ring-0"
                        >
                            {{ theme('hero_secondary_text', 'Explore Services') }}
                        </a>
                    </div>
                </div>
            </div>
        </section>

        {{-- SERVICES / INFRASTRUCTURE ) --}}
        <section id="services" class="relative py-28 md:py-32 lg:py-32">
            <div class="container">
                <div class="mx-auto mb-16 max-w-[740px] text-center md:mb-20">
                    <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                        {{ theme('infrastructure_title', theme('services_title', 'Choose Your Infrastructure')) }}
                    </h2>
                    <p class="mx-auto mt-4 max-w-[620px] text-sm text-base/60 sm:text-base">
                        {{ theme('infrastructure_subtitle', theme('services_subtitle', 'Three specialized platforms. One engineered foundation.')) }}
                    </p>
                </div>

                <div class="mx-auto grid max-w-[1200px] gap-8 lg:grid-cols-3">
                    @for ($i = 1; $i <= 3; $i++)
                        @php
                            $title = trim((string) theme("infrastructure_card_{$i}_title", ''));
                            $desc  = trim((string) theme("infrastructure_card_{$i}_description", ''));

                            $imgPath = trim((string) theme("infrastructure_card_{$i}_image", ''));
                            $imgUrl = $imgPath !== '' ? Storage::url($imgPath) : '';

                            $btnText = trim((string) theme("infrastructure_card_{$i}_button_text", ''));
                            if ($btnText === '') $btnText = 'View Plans';

                            $btnHref = trim((string) theme("infrastructure_card_{$i}_button_href", ''));
                            if ($btnHref === '') $btnHref = $primaryCategoryUrl;

                            $hasAny = ($title !== '') || ($desc !== '') || ($imgUrl !== '');
                        @endphp

                        @if($hasAny)
                            @if($fullscreenCards)
                                <div
                                    class="group relative flex h-full min-h-[420px] flex-col overflow-hidden rounded-2xl border border-neutral/25 bg-background-secondary/35 transition hover:bg-background-secondary/50"
                                    @if($imgUrl !== '')
                                        style="background-image:url('{{ $imgUrl }}'); background-size:cover; background-position:center;"
                                    @endif
                                >
                                    <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-black/30 via-black/45 to-black/70"></div>

                                    <div class="relative z-10 flex flex-1 flex-col p-8 text-center">
                                        <div>
                                            @if($title !== '')
                                                <h3 class="text-xl font-semibold tracking-tight">
                                                    {{ $title }}
                                                </h3>
                                            @endif

                                            @if($desc !== '')
                                                <p class="mx-auto mt-4 max-w-[90%] text-sm text-base/80">
                                                    {{ $desc }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="mt-auto pt-8">
                                            <a
                                                href="{{ $btnHref }}"
                                                class="inline-flex w-full items-center justify-center rounded-full bg-black/25 px-8 py-3.5 text-sm font-semibold text-base backdrop-blur transition hover:bg-black/35 focus:outline-none focus:ring-0"
                                            >
                                                {{ $btnText }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="group flex h-full flex-col overflow-hidden rounded-2xl border border-neutral/25 bg-background-secondary/35 transition hover:bg-background-secondary/50">
                                    <div class="w-full">
                                        <div class="aspect-[16/9] w-full overflow-hidden bg-background-secondary/55">
                                            @if($imgUrl !== '')
                                                <img
                                                    src="{{ $imgUrl }}"
                                                    alt="{{ $title !== '' ? $title : 'Infrastructure' }}"
                                                    class="h-full w-full object-cover"
                                                    loading="lazy"
                                                >
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex flex-1 flex-col p-8 text-center">
                                        <div>
                                            @if($title !== '')
                                                <h3 class="text-xl font-semibold tracking-tight">
                                                    {{ $title }}
                                                </h3>
                                            @endif

                                            @if($desc !== '')
                                                <p class="mx-auto mt-4 max-w-[90%] text-sm text-base/60">
                                                    {{ $desc }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="mt-auto pt-8">
                                            <a
                                                href="{{ $btnHref }}"
                                                class="inline-flex w-full items-center justify-center rounded-full bg-background-secondary/35 px-8 py-3.5 text-sm font-semibold text-base transition hover:bg-background-secondary/55 focus:outline-none focus:ring-0"
                                            >
                                                {{ $btnText }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endfor
                </div>
            </div>
        </section>

        {{-- WHY OBSIDIAN --}}
        @include('partials.home-why-obsidian')

        {{-- STATS STRIP (one line, tracks theme.php) --}}
        @includeIf('partials.home-stats-strip')

        {{-- TRUST STRIP --}}
        @includeIf('partials.home-trust-strip')

        {{-- PRICING --}}
        @include('partials.home-pricing', ['primaryCategoryUrl' => $primaryCategoryUrl])

        {{-- VIDEO HUB --}}
        @includeIf('partials.home-video-hub')
    </div>

    {!! hook('pages.home') !!}
</div>
