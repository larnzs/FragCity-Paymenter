{{-- extensions/Others/ObsidianTermsAndCondition/Resources/views/front/terms.blade.php --}}

@php
    use Illuminate\Support\Facades\DB;

    /**
     * Self-contained config loader:
     * - If controller/route passes $cfg, use it.
     * - Otherwise load obsidian.terms from settings and decode JSON.
     */
    if (!isset($cfg) || !is_array($cfg)) {
        $raw = DB::table('settings')->where('key', 'obsidian.terms')->value('value');
        $decoded = null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
        }

        $cfg = is_array($decoded) ? $decoded : [];
    }

    $seoTitle       = (string) data_get($cfg, 'seo.title', 'Terms & Conditions');
    $seoDescription = (string) data_get($cfg, 'seo.description', '');
    $robots         = (string) data_get($cfg, 'seo.robots', 'index,follow');
    $canonical      = (string) data_get($cfg, 'seo.canonical_url', '');

    $heroEnabled = (bool) data_get($cfg, 'hero.enabled', true);
    $heroTitle   = (string) data_get($cfg, 'hero.title', 'Terms & Conditions');
    $heroSummary = (string) data_get($cfg, 'hero.summary', 'Please read these terms carefully before using our services.');

    $lastUpdatedEnabled = (bool) data_get($cfg, 'hero.last_updated.enabled', true);
    $lastUpdatedLabel   = (string) data_get($cfg, 'hero.last_updated.label', 'Last updated');
    $lastUpdatedDate    = (string) data_get($cfg, 'hero.last_updated.date', '');
    $lastUpdatedDisplay = (string) data_get($cfg, 'hero.last_updated.display', '');

    $actionsEnabled = (bool) data_get($cfg, 'hero.actions.enabled', true);
    $buttons        = (array) data_get($cfg, 'hero.actions.buttons', []);

    $tocEnabled = (bool) data_get($cfg, 'toc.enabled', true);
    $tocTitle   = (string) data_get($cfg, 'toc.title', '');
    if (trim($tocTitle) === '') {
        $tocTitle = 'Table of contents';
    }
    $tocMobileCollapsible = (bool) data_get($cfg, 'toc.mobile_collapse.enabled', true);

    $sectionsAll = (array) data_get($cfg, 'sections', []);
    $sections = [];
    foreach ($sectionsAll as $s) {
        if ((bool) data_get($s, 'enabled', true) === true) {
            $sections[] = $s;
        }
    }

    $footerEnabled = (bool) data_get($cfg, 'footer.enabled', true);
    $footerLeft    = (string) data_get($cfg, 'footer.left_text', '');
    $footerLinks   = (array) data_get($cfg, 'footer.links', []);

    $makeId = function ($fallbackTitle, $fallbackNumber) {
        $base = trim((string) $fallbackTitle);
        if ($base === '') $base = 'section-' . (string) $fallbackNumber;

        $base = strtolower($base);
        $base = preg_replace('/[^a-z0-9]+/', '-', $base);
        $base = trim($base, '-');

        return $base !== '' ? $base : ('section-' . (string) $fallbackNumber);
    };

    $formatDate = function ($dateStr) {
        $dateStr = trim((string) $dateStr);
        if ($dateStr === '') return '';
        return $dateStr;
    };

    // Filter enabled hero buttons
    $enabledButtons = [];
    foreach ($buttons as $b) {
        if ((bool) data_get($b, 'enabled', true) === true) $enabledButtons[] = $b;
    }

    // Build TOC items from enabled sections
    $tocItems = [];
    foreach ($sections as $idx => $s) {
        // Check if section should be visible in TOC (default true)
        $visibleInToc = data_get($s, 'visible_in_toc');
        if ($visibleInToc === false) {
            continue;
        }

        $n = (string) data_get($s, 'number', (string) ($idx + 1));
        $t = (string) data_get($s, 'title', 'Section');
        $sid = (string) data_get($s, 'id', '');

        // Generate ID if empty - MUST match the section anchor ID logic
        if (trim($sid) === '') {
            $sid = $makeId($t, $n);
        }

        $tocItems[] = ['n' => $n, 't' => $t, 'id' => $sid];
    }

    $calloutMeta = [
        'info' => [
            'border' => 'border-l-blue-500',
            'bg' => 'bg-blue-500/5',
            'pill' => 'bg-blue-500/20 text-blue-300 border-blue-400/30',
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>',
            'label'=> 'Info',
        ],
        'warning' => [
            'border' => 'border-l-amber-500',
            'bg' => 'bg-amber-500/5',
            'pill' => 'bg-amber-500/20 text-amber-300 border-amber-400/30',
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>',
            'label'=> 'Warning',
        ],
        'definition' => [
            'border' => 'border-l-emerald-500',
            'bg' => 'bg-emerald-500/5',
            'pill' => 'bg-emerald-500/20 text-emerald-300 border-emerald-400/30',
            'icon' => '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
            'label'=> 'Definition',
        ],
    ];
@endphp

<x-app-layout>
    @push('head')
        <title>{{ $seoTitle }}</title>
        <meta name="robots" content="{{ $robots }}">
        @if($seoDescription !== '')
            <meta name="description" content="{{ $seoDescription }}">
        @endif
        @if($canonical !== '')
            <link rel="canonical" href="{{ $canonical }}">
        @endif

        <style>
            .smooth-scroll {
                scroll-behavior: smooth;
            }
        </style>
    @endpush

    {{-- IMPORTANT: Do NOT force a page background. Let the theme/user background show through. --}}
    <div class="min-h-screen bg-transparent text-white relative overflow-hidden smooth-scroll">
        <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 pt-8 lg:pt-16 pb-16">
            {{-- Hero Section --}}
            @if($heroEnabled)
                <div class="bg-[#0b0b12]/90 border border-white/10 rounded-2xl shadow-xl shadow-black/20 overflow-hidden mb-8 mx-auto max-w-4xl">
                    <div class="p-6 lg:p-10">
                        @if($lastUpdatedEnabled)
                            @php
                                $display = $lastUpdatedDisplay !== '' ? $lastUpdatedDisplay : $formatDate($lastUpdatedDate);
                            @endphp
                            @if($display !== '')
                                <div class="inline-flex items-center gap-2 rounded-full border border-purple-500/30 bg-purple-500/10 px-4 py-1.5 text-sm mb-4">
                                    <svg class="w-3.5 h-3.5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <span class="text-white/70">{{ $lastUpdatedLabel }}:</span>
                                    <span class="font-medium text-white">{{ $display }}</span>
                                </div>
                            @endif
                        @endif

                        <h1 class="text-3xl lg:text-5xl font-bold tracking-tight text-white mb-4">
                            {{ $heroTitle }}
                        </h1>

                        @if(trim($heroSummary) !== '')
                            <p class="text-base lg:text-lg text-white/60 leading-relaxed max-w-3xl">
                                {{ $heroSummary }}
                            </p>
                        @endif

                        @if($actionsEnabled && count($enabledButtons) > 0)
                            <div class="mt-6 flex flex-wrap gap-3">
                                @foreach($enabledButtons as $b)
                                    @php
                                        $label = (string) data_get($b, 'label', 'Button');
                                        $url   = (string) data_get($b, 'url', '#');
                                        $style = (string) data_get($b, 'style', 'primary');
                                        $newTab = (bool) data_get($b, 'new_tab', false);

                                        if ($style === 'primary') {
                                            $btnClass = 'inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-medium shadow-lg shadow-purple-600/30 hover:shadow-purple-600/50 transition-all duration-200';
                                        } elseif ($style === 'secondary') {
                                            $btnClass = 'inline-flex items-center gap-2 px-5 py-2.5 rounded-lg border border-white/20 bg-white/5 hover:bg-white/10 text-white font-medium transition-all duration-200';
                                        } else {
                                            $btnClass = 'inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white/70 hover:text-white hover:bg-white/5 font-medium transition-all duration-200';
                                        }
                                    @endphp

                                    <a href="{{ $url }}"
                                       class="{{ $btnClass }}"
                                       @if($newTab) target="_blank" rel="noopener noreferrer" @endif>
                                        {{ $label }}
                                        @if($newTab)
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                            </svg>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Table of Contents --}}
            @if($tocEnabled && count($tocItems) > 0)
                <div class="bg-[#0b0b12]/90 border border-white/10 rounded-2xl shadow-xl shadow-black/20 overflow-hidden mb-8 mx-auto max-w-4xl">
                    <div class="p-5">
                        @if($tocMobileCollapsible)
                            <details class="group" open>
                                <summary class="cursor-pointer list-none select-none flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-white">{{ $tocTitle }}</h3>
                                    <svg class="w-5 h-5 text-white/50 group-open:rotate-180 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </summary>

                                <nav class="mt-4 space-y-1">
                                    @foreach($tocItems as $it)
                                        <a href="#{{ $it['id'] }}"
                                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/60 hover:text-white hover:bg-white/5 transition-all text-sm">
                                            <span class="text-white/40 font-medium">{{ $it['n'] }}.</span>
                                            <span class="flex-1">{{ $it['t'] }}</span>
                                        </a>
                                    @endforeach
                                </nav>
                            </details>
                        @else
                            <h3 class="text-lg font-semibold text-white mb-4">{{ $tocTitle }}</h3>
                            <nav class="space-y-1">
                                @foreach($tocItems as $it)
                                    <a href="#{{ $it['id'] }}"
                                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white/60 hover:text-white hover:bg-white/5 transition-all text-sm">
                                        <span class="text-white/40 font-medium">{{ $it['n'] }}.</span>
                                        <span class="flex-1">{{ $it['t'] }}</span>
                                    </a>
                                @endforeach
                            </nav>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Content Grid --}}
            <div class="space-y-6 mx-auto max-w-4xl">
                @forelse($sections as $idx => $section)
                    @php
                        $num    = (string) data_get($section, 'number', (string) ($idx + 1));
                        $stitle = (string) data_get($section, 'title', 'Section');
                        $id     = (string) data_get($section, 'id', '');
                        $blocks = (array) data_get($section, 'blocks', []);

                        // Generate ID if empty - MUST match TOC logic
                        if (trim($id) === '') {
                            $id = $makeId($stitle, $num);
                        }

                        $blocksEnabled = [];
                        foreach ($blocks as $bl) {
                            if ((bool) data_get($bl, 'enabled', true) === true) $blocksEnabled[] = $bl;
                        }
                    @endphp

                    <section id="{{ $id }}" class="bg-[#0b0b12]/90 border border-white/10 rounded-2xl shadow-xl shadow-black/20 overflow-hidden scroll-mt-8">
                        <div class="p-6 lg:p-8">
                            <div class="flex items-baseline gap-3 mb-6">
                                <span class="text-purple-400 font-semibold text-lg">{{ $num }}.</span>
                                <h2 class="text-2xl lg:text-3xl font-bold text-white flex-1">{{ $stitle }}</h2>
                            </div>

                            <div class="space-y-5 text-white/70 leading-relaxed">
                                @foreach($blocksEnabled as $block)
                                    @php
                                        $type = (string) data_get($block, 'type', 'paragraph');
                                    @endphp

                                    @if($type === 'heading')
                                        @php $text = (string) data_get($block, 'text', ''); @endphp
                                        @if(trim($text) !== '')
                                            <h3 class="text-xl font-bold text-white mt-6 first:mt-0">
                                                {{ $text }}
                                            </h3>
                                        @endif

                                    @elseif($type === 'paragraph')
                                        @php $text = (string) data_get($block, 'text', ''); @endphp
                                        @if(trim($text) !== '')
                                            <p class="text-white/70 leading-relaxed">
                                                {{ $text }}
                                            </p>
                                        @endif

                                    @elseif($type === 'bullets')
                                        @php
                                            $items = (array) data_get($block, 'items', []);
                                            $itemsEnabled = [];
                                            foreach ($items as $it) {
                                                $t = is_array($it) ? (string) data_get($it, 'text', '') : (string) $it;
                                                if (trim($t) !== '') $itemsEnabled[] = $t;
                                            }
                                        @endphp

                                        @if(count($itemsEnabled) > 0)
                                            <div class="bg-white/[0.02] rounded-xl p-5">
                                                <ul class="space-y-3">
                                                    @foreach($itemsEnabled as $t)
                                                        <li class="flex gap-3 items-start">
                                                            <span class="text-purple-400 mt-1.5">•</span>
                                                            <span class="flex-1 text-white/70">{{ $t }}</span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                    @elseif($type === 'callout')
                                        @php
                                            $variant = (string) data_get($block, 'variant', 'info');
                                            if (!array_key_exists($variant, $calloutMeta)) $variant = 'info';

                                            $meta   = $calloutMeta[$variant];
                                            $ctitle = (string) data_get($block, 'title', '');
                                            $text   = (string) data_get($block, 'text', '');
                                        @endphp

                                        <div class="border-l-4 {{ $meta['border'] }} {{ $meta['bg'] }} rounded-r-xl p-5">
                                            <div class="flex items-start gap-3">
                                                <div class="text-white/80 mt-0.5">
                                                    {!! $meta['icon'] !!}
                                                </div>

                                                <div class="flex-1">
                                                    <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full {{ $meta['pill'] }} text-xs font-semibold mb-2">
                                                        {{ $meta['label'] }}
                                                    </div>

                                                    @if(trim($ctitle) !== '')
                                                        <div class="text-base font-bold text-white mb-1">
                                                            {{ $ctitle }}
                                                        </div>
                                                    @endif

                                                    @if(trim($text) !== '')
                                                        <div class="text-sm text-white/80 leading-relaxed">
                                                            {{ $text }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </section>
                @empty
                    <div class="bg-[#0b0b12]/90 border border-white/10 rounded-2xl p-12 text-center">
                        <svg class="w-16 h-16 mx-auto text-white/20 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-white/50 font-medium">No terms content has been configured yet.</p>
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            @if($footerEnabled)
                <div class="mt-12 bg-[#0b0b12]/90 border border-white/10 rounded-2xl shadow-xl shadow-black/20 overflow-hidden mx-auto max-w-4xl">
                    <div class="p-6 lg:p-8 flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
                        <div class="text-white/60 text-sm">
                            {{ $footerLeft }}
                        </div>

                        @if(count($footerLinks) > 0)
                            <div class="flex flex-wrap gap-3">
                                @foreach($footerLinks as $l)
                                    @php
                                        if ((bool) data_get($l, 'enabled', true) !== true) continue;
                                        $label = (string) data_get($l, 'label', 'Link');
                                        $url   = (string) data_get($l, 'url', '#');
                                        $newTab = (bool) data_get($l, 'new_tab', false);
                                    @endphp

                                    <a href="{{ $url }}"
                                       class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-white/10 bg-white/5 text-white/70 hover:text-white hover:bg-white/10 transition-all text-sm font-medium"
                                       @if($newTab) target="_blank" rel="noopener noreferrer" @endif>
                                        {{ $label }}
                                        @if($newTab)
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                            </svg>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
