<section id="get-started" class="relative py-32">
    <div class="container">
        @php
            $ctaUrl = $primaryCategoryUrl ?? route('home');
            $ctaOverride = trim((string) theme('cta_button_href', ''));
            if ($ctaOverride !== '') {
                $ctaUrl = $ctaOverride;
            }
        @endphp

        <div class="mx-auto max-w-[900px] text-center">
            <h2 class="mb-8 text-4xl tracking-tight leading-[1.1] sm:text-5xl lg:text-6xl">
                {{ theme('cta_title', 'Deploy Your Infrastructure') }}
            </h2>

            <p class="mx-auto mb-12 max-w-[600px] text-lg leading-relaxed text-base/60 sm:text-xl">
                {{ theme('cta_subtitle', 'Join thousands of developers and businesses running on Obsidian. Start in under a minute.') }}
            </p>

            <a
                href="{{ $ctaUrl }}"
                wire:navigate
                class="inline-flex items-center justify-center rounded-full bg-base px-12 py-4 font-semibold text-background transition-colors hover:bg-base/90"
            >
                {{ theme('cta_button_text', 'Get Started Now') }}
            </a>

            <div class="mt-12 text-sm text-base/40">
                {{ theme('cta_trust', 'No credit card required. 7-day money-back guarantee') }}
            </div>
        </div>
    </div>

    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute left-1/2 top-1/2 h-[800px] w-[800px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-neutral/20"></div>
    </div>
</section>
