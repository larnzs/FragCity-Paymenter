{{-- obsidian/views/components/navigation/footer.blade.php --}}
@php
    use Illuminate\Support\Facades\Auth;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\Facades\Storage;

    $mode = Auth::check() ? 'auth' : 'guest';

    $passesVisibility = function ($visibility, $mode) {
        $v = (string) ($visibility ?? 'always');
        if ($v === 'guest') return $mode === 'guest';
        if ($v === 'auth') return $mode === 'auth';
        return true; // always
    };

    /*
     |--------------------------------------------------------------------------
     | Footer Editor (DB JSON) - obsidian.footer
     |--------------------------------------------------------------------------
     | If present, this becomes the source of truth for:
     | - enabled
     | - columns
     | - links
     | - visibility rules
     | - optional copyright text
     |
     | If not present, we fall back to existing theme() settings.
     */
    $dbFooter = [];
    $dbRaw = DB::table('settings')->where('key', 'obsidian.footer')->value('value');
    if (is_string($dbRaw) && trim($dbRaw) !== '') {
        $decoded = json_decode($dbRaw, true);
        if (is_array($decoded)) {
            $dbFooter = $decoded;
        }
    }

    $hasDbFooter = is_array($dbFooter) && !empty($dbFooter);

    // If DB footer exists, it controls global enabled.
    // Otherwise fallback to your theme() switch.
    if ($hasDbFooter) {
        $enabled = (bool) ($dbFooter['enabled'] ?? true);
    } else {
        $enabled = (bool) theme('footer_enabled', true);
    }

    if (!$enabled) return;

    // Brand area (keep theme-driven for now; Footer Editor currently manages columns/links)
    $brandName    = trim((string) theme('footer_brand_name', config('app.name')));
    $brandTagline = trim((string) theme('footer_tagline', 'Infrastructure forged in precision.'));
    $brandHref    = trim((string) theme('footer_brand_href', '/'));

    // Logo options (NOW supports uploaded footer_logo from theme.php)
    $showLogo = (bool) theme('footer_show_logo', true);
    $logoOnly = theme('logo_display', 'logo-and-name') === 'logo-only';

    $footerLogoPath = trim((string) theme('footer_logo', ''));
    $footerLogoUrl  = $footerLogoPath !== '' ? Storage::url($footerLogoPath) : '';

    /*
     |--------------------------------------------------------------------------
     | Columns + links
     |--------------------------------------------------------------------------
     | DB footer format:
     | columns: [
     |   [
     |     title, enabled, visible, visibility,
     |     links: [
     |       label, url, type, enabled, visible, visibility
     |     ]
     |   ]
     | ]
     |
     | Theme footer format (existing):
     | columns: [
     |   [
     |     enabled, title, links: [ enabled, label, href ]
     |   ]
     | ]
     */

    $columns = [];

    if ($hasDbFooter) {
        $rawCols = $dbFooter['columns'] ?? [];
        $rawCols = is_array($rawCols) ? $rawCols : [];

        foreach ($rawCols as $col) {
            $colEnabled = (bool) ($col['enabled'] ?? true);
            $colVisible = (bool) ($col['visible'] ?? true);
            $colVisRule = (string) ($col['visibility'] ?? 'always');

            if (!$colEnabled || !$colVisible) continue;
            if (!$passesVisibility($colVisRule, $mode)) continue;

            $title = trim((string) ($col['title'] ?? ''));
            $links = $col['links'] ?? [];
            $links = is_array($links) ? $links : [];

            $outLinks = [];
            foreach ($links as $link) {
                $lEnabled = (bool) ($link['enabled'] ?? true);
                $lVisible = (bool) ($link['visible'] ?? true);
                $lVisRule = (string) ($link['visibility'] ?? 'always');

                if (!$lEnabled || !$lVisible) continue;
                if (!$passesVisibility($lVisRule, $mode)) continue;

                $label = trim((string) ($link['label'] ?? ''));
                $url   = trim((string) ($link['url'] ?? ''));
                $type  = (string) ($link['type'] ?? 'internal');

                if ($label === '' || $url === '') continue;

                $outLinks[] = [
                    'enabled' => true,
                    'label'   => $label,
                    'href'    => $url,
                    'type'    => $type,
                ];
            }

            $columns[] = [
                'enabled' => true,
                'title'   => $title,
                'links'   => $outLinks,
            ];
        }
    } else {
        // Existing theme() fallback (unchanged)
        $columns = [
            [
                'enabled' => (bool) theme('footer_col_1_enabled', true),
                'title'   => trim((string) theme('footer_col_1_title', 'Services')),
                'links'   => [
                    ['enabled' => (bool) theme('footer_col_1_link_1_enabled', true), 'label' => trim((string) theme('footer_col_1_link_1_label', 'Game Servers')),      'href' => trim((string) theme('footer_col_1_link_1_href', '#game-servers'))],
                    ['enabled' => (bool) theme('footer_col_1_link_2_enabled', true), 'label' => trim((string) theme('footer_col_1_link_2_label', 'VPS')),              'href' => trim((string) theme('footer_col_1_link_2_href', '#vps'))],
                    ['enabled' => (bool) theme('footer_col_1_link_3_enabled', true), 'label' => trim((string) theme('footer_col_1_link_3_label', 'Web Hosting')),       'href' => trim((string) theme('footer_col_1_link_3_href', '#web-hosting'))],
                    ['enabled' => (bool) theme('footer_col_1_link_4_enabled', true), 'label' => trim((string) theme('footer_col_1_link_4_label', 'Dedicated Servers')), 'href' => trim((string) theme('footer_col_1_link_4_href', '#dedicated'))],
                ],
            ],
            [
                'enabled' => (bool) theme('footer_col_2_enabled', true),
                'title'   => trim((string) theme('footer_col_2_title', 'Company')),
                'links'   => [
                    ['enabled' => (bool) theme('footer_col_2_link_1_enabled', true), 'label' => trim((string) theme('footer_col_2_link_1_label', 'About')),   'href' => trim((string) theme('footer_col_2_link_1_href', '#about'))],
                    ['enabled' => (bool) theme('footer_col_2_link_2_enabled', true), 'label' => trim((string) theme('footer_col_2_link_2_label', 'Status')),  'href' => trim((string) theme('footer_col_2_link_2_href', '#status'))],
                    ['enabled' => (bool) theme('footer_col_2_link_3_enabled', true), 'label' => trim((string) theme('footer_col_2_link_3_label', 'Careers')), 'href' => trim((string) theme('footer_col_2_link_3_href', '#careers'))],
                    ['enabled' => (bool) theme('footer_col_2_link_4_enabled', true), 'label' => trim((string) theme('footer_col_2_link_4_label', 'Blog')),    'href' => trim((string) theme('footer_col_2_link_4_href', '#blog'))],
                ],
            ],
            [
                'enabled' => (bool) theme('footer_col_3_enabled', true),
                'title'   => trim((string) theme('footer_col_3_title', 'Support')),
                'links'   => [
                    ['enabled' => (bool) theme('footer_col_3_link_1_enabled', true), 'label' => trim((string) theme('footer_col_3_link_1_label', 'Docs')),          'href' => trim((string) theme('footer_col_3_link_1_href', '#docs'))],
                    ['enabled' => (bool) theme('footer_col_3_link_2_enabled', true), 'label' => trim((string) theme('footer_col_3_link_2_label', 'Contact')),       'href' => trim((string) theme('footer_col_3_link_2_href', '#contact'))],
                    ['enabled' => (bool) theme('footer_col_3_link_3_enabled', true), 'label' => trim((string) theme('footer_col_3_link_3_label', 'Knowledgebase')), 'href' => trim((string) theme('footer_col_3_link_3_href', '#knowledgebase'))],
                    ['enabled' => (bool) theme('footer_col_3_link_4_enabled', true), 'label' => trim((string) theme('footer_col_3_link_4_label', 'System Status')),  'href' => trim((string) theme('footer_col_3_link_4_href', '#system-status'))],
                ],
            ],
            [
                'enabled' => (bool) theme('footer_col_4_enabled', true),
                'title'   => trim((string) theme('footer_col_4_title', 'Legal')),
                'links'   => [
                    ['enabled' => (bool) theme('footer_col_4_link_1_enabled', true), 'label' => trim((string) theme('footer_col_4_link_1_label', 'Terms')),         'href' => trim((string) theme('footer_col_4_link_1_href', '#terms'))],
                    ['enabled' => (bool) theme('footer_col_4_link_2_enabled', true), 'label' => trim((string) theme('footer_col_4_link_2_label', 'Privacy')),        'href' => trim((string) theme('footer_col_4_link_2_href', '#privacy'))],
                    ['enabled' => (bool) theme('footer_col_4_link_3_enabled', true), 'label' => trim((string) theme('footer_col_4_link_3_label', 'Refund Policy')), 'href' => trim((string) theme('footer_col_4_link_3_href', '#refund'))],
                ],
            ],
        ];
    }

    // Bottom row
    $showPoweredBy = (bool) theme('footer_powered_by_enabled', true);

    // Optional copyright override from DB footer
    $dbCopyright = '';
    if ($hasDbFooter) {
        $dbCopyright = trim((string) ($dbFooter['copyright'] ?? ''));
    }

    // Socials (keep theme-driven for now)
    $socials = [
        [
            'enabled' => (bool) theme('footer_social_github_enabled', true),
            'href'    => trim((string) theme('footer_social_github_href', '#github')),
            'label'   => 'GitHub',
            'svg'     => '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 19c-4.5 1.5-4.5-2.5-6-3m12 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/></svg>',
        ],
        [
            'enabled' => (bool) theme('footer_social_twitter_enabled', true),
            'href'    => trim((string) theme('footer_social_twitter_href', '#twitter')),
            'label'   => 'Twitter',
            'svg'     => '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2-1.9-.2-3.5-1.5-4-3.4.6.1 1.2.1 1.8-.1-2-.4-3.4-2.2-3.3-4.2.6.3 1.3.5 2 .5C2.7 8 3.1 5.4 5 4c2.2 2.7 5.5 4.4 9.2 4.2.1-.5.1-1 .1-1.5 0-2.4 2-4.3 4.3-4.3.8 0 1.6.3 2.2.9.7-.1 1.4-.4 2-.8z"/></svg>',
        ],
        [
            'enabled' => (bool) theme('footer_social_linkedin_enabled', true),
            'href'    => trim((string) theme('footer_social_linkedin_href', '#linkedin')),
            'label'   => 'LinkedIn',
            'svg'     => '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7H10V9h4v2a4 4 0 0 1 2-3z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>',
        ],
    ];
    $anySocial = collect($socials)->contains(fn ($s) => !empty($s['enabled']) && trim((string)($s['href'] ?? '')) !== '');
@endphp

<footer class="relative mt-24 pt-16 pb-8 w-full">
    {{-- Subtle gradient fade at top (full width) --}}
    <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-neutral/40 to-transparent"></div>

    {{-- Figma uses a max width + centered wrapper. This prevents the “hugged in the corner” issue. --}}
    <div class="mx-auto w-full max-w-[1400px] px-6 lg:px-8">
        {{-- Top Row - Logo & Tagline --}}
        <div class="mb-16">
            <a href="{{ $brandHref !== '' ? $brandHref : '/' }}" class="inline-flex items-center gap-2">
                @if($showLogo)
                    @if($footerLogoUrl !== '')
                        <img
                            src="{{ $footerLogoUrl }}"
                            alt="{{ $brandName !== '' ? $brandName : config('app.name') }}"
                            class="h-8 w-auto rounded-md"
                            loading="lazy"
                        >
                    @else
                        {{-- Fallback to app logo component if no footer_logo uploaded --}}
                        <x-logo class="h-8 w-8" />
                    @endif

                    @if(!$logoOnly && $brandName !== '')
                        <span class="text-base font-semibold tracking-tight text-base">
                            {{ $brandName }}
                        </span>
                    @endif
                @else
                    @if(!$logoOnly && $brandName !== '')
                        <span class="text-base font-semibold tracking-tight text-base">
                            {{ $brandName }}
                        </span>
                    @endif
                @endif
            </a>

            @if($brandTagline !== '')
                <p class="mt-3 max-w-md text-base/60">
                    {{ $brandTagline }}
                </p>
            @endif
        </div>

        {{-- Footer Columns --}}
        <div class="grid grid-cols-2 gap-12 mb-16 md:grid-cols-4">
            @foreach($columns as $col)
                @php
                    $colEnabled = (bool) ($col['enabled'] ?? true);
                    $colTitle   = trim((string) ($col['title'] ?? ''));
                    $links      = $col['links'] ?? [];
                    $anyLinks   = collect($links)->contains(fn ($l) => !empty($l['enabled']) && trim((string)($l['label'] ?? '')) !== '' && trim((string)($l['href'] ?? '')) !== '');
                @endphp

                @if($colEnabled && ($colTitle !== '' || $anyLinks))
                    <div>
                        @if($colTitle !== '')
                            <h4 class="mb-6 text-sm font-semibold tracking-tight text-base">
                                {{ $colTitle }}
                            </h4>
                        @endif

                        @if($anyLinks)
                            <ul class="space-y-4">
                                @foreach($links as $link)
                                    @php
                                        $lEnabled = (bool) ($link['enabled'] ?? true);
                                        $lLabel = trim((string) ($link['label'] ?? ''));
                                        $lHref  = trim((string) ($link['href'] ?? ''));
                                        $lType  = (string) ($link['type'] ?? 'internal');
                                    @endphp

                                    @if($lEnabled && $lLabel !== '' && $lHref !== '')
                                        <li>
                                            <a
                                                href="{{ $lHref }}"
                                                class="text-sm text-base/60 transition-colors duration-200 hover:text-base"
                                                @if($lType === 'external' || str_starts_with($lHref, 'http')) target="_blank" rel="noopener noreferrer" @endif
                                            >
                                                {{ $lLabel }}
                                            </a>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        {{-- Bottom Row --}}
        <div class="pt-8 border-t border-neutral/40 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex flex-col md:flex-row items-center gap-4 text-base/60">
                @if($dbCopyright !== '')
                    <p>{{ $dbCopyright }}</p>
                @else
                    <p>&copy; {{ date('Y') }} {{ $brandName !== '' ? $brandName : config('app.name') }}</p>
                @endif

                <span class="hidden md:block text-neutral/50">•</span>

                @if($showPoweredBy)
                    {{-- Paymenter is free and opensource, removing this link is not cool --}}
                    <a
                        href="https://paymenter.org"
                        target="_blank"
                        rel="noreferrer"
                        class="text-xs text-base/55 hover:text-base transition-colors"
                    >
                        Powered by Paymenter
                    </a>
                @endif
            </div>

            @if($anySocial)
                <div class="flex items-center gap-4">
                    @foreach($socials as $s)
                        @php
                            $sEnabled = (bool) ($s['enabled'] ?? false);
                            $sHref    = trim((string) ($s['href'] ?? ''));
                            $sLabel   = trim((string) ($s['label'] ?? ''));
                            $sSvg     = (string) ($s['svg'] ?? '');
                        @endphp

                        @if($sEnabled && $sHref !== '')
                            <a
                                href="{{ $sHref }}"
                                class="w-9 h-9 rounded-lg bg-background-secondary/30 hover:bg-background-secondary/50 flex items-center justify-center text-base/60 hover:text-base transition-all duration-200"
                                aria-label="{{ $sLabel !== '' ? $sLabel : 'Social' }}"
                                @if(str_starts_with($sHref, 'http')) target="_blank" rel="noopener noreferrer" @endif
                            >
                                {!! $sSvg !!}
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</footer>
