<section class="w-full">
    <div class="container">
        {{-- Centered title --}}
        <div class="mb-10 mx-auto max-w-2xl text-center">
            <h2 class="text-2xl font-semibold tracking-tight md:text-3xl">
                {{ theme('features_title', 'Why Obsidian') }}
            </h2>
            <p class="mt-2 text-base text-foreground/70">
                {{ theme('features_subtitle', 'Infrastructure engineered for stability, performance, and scale.') }}
            </p>
        </div>

        @php
            $features = collect([
                [
                    'enabled' => (bool) theme('feature_one_enabled', true),
                    'title'   => theme('feature_one_title', 'Enterprise Hardware'),
                    'text'    => theme('feature_one_text', 'High-performance CPUs and NVMe storage deployed across all regions.'),
                ],
                [
                    'enabled' => (bool) theme('feature_two_enabled', true),
                    'title'   => theme('feature_two_title', 'Instant Provisioning'),
                    'text'    => theme('feature_two_text', 'Your services are deployed automatically within seconds of checkout.'),
                ],
                [
                    'enabled' => (bool) theme('feature_three_enabled', true),
                    'title'   => theme('feature_three_title', 'Global Network'),
                    'text'    => theme('feature_three_text', 'Multiple locations worldwide for low latency and reliability.'),
                ],
                [
                    'enabled' => (bool) theme('feature_four_enabled', true),
                    'title'   => theme('feature_four_title', 'DDoS Protection'),
                    'text'    => theme('feature_four_text', 'Always-on mitigation to keep services online during attacks and traffic spikes.'),
                ],
                [
                    'enabled' => (bool) theme('feature_five_enabled', true),
                    'title'   => theme('feature_five_title', '24/7 Expert Support'),
                    'text'    => theme('feature_five_text', 'Real humans, fast response times, and help that actually solves the problem.'),
                ],
                [
                    'enabled' => (bool) theme('feature_six_enabled', true),
                    'title'   => theme('feature_six_title', 'Simple Management'),
                    'text'    => theme('feature_six_text', 'A clean panel that makes deploying, upgrading, and managing services effortless.'),
                ],
            ])->filter(fn ($f) => $f['enabled'])
              ->values();
        @endphp

        {{-- Feature cards (auto fits 1-6) --}}
        @if($features->count() > 0)
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($features as $feature)
                    <div class="rounded-2xl border border-neutral bg-background-secondary p-6">
                        <h3 class="text-lg font-semibold tracking-tight">
                            {{ $feature['title'] }}
                        </h3>
                        <p class="mt-2 text-sm text-foreground/70">
                            {{ $feature['text'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
