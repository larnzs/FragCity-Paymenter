@php
    $cfg = is_array($about ?? null)
        ? $about
        : \Paymenter\Extensions\Others\ObsidianAboutPage\Livewire\AboutPageEditor::defaultConfig();

    $publicBase = trim('assets/images', '/');

    $seoTitle = (string) data_get($cfg, 'seo.title', 'About');
    $seoDesc  = (string) data_get($cfg, 'seo.description', '');

    $heroEnabled = (bool) data_get($cfg, 'hero.enabled', true);
    $heroTitle   = (string) data_get($cfg, 'hero.title', 'About Us');
    $heroSub     = (string) data_get($cfg, 'hero.subtitle', '');

    $heroFile = (string) data_get($cfg, 'hero.image.file', '');
    $heroPath = (string) data_get($cfg, 'hero.image.path', '');
    $heroUrl  = (string) data_get($cfg, 'hero.image.url', '');

    $heroImg = '';
    if ($heroFile !== '') {
        $heroImg = asset($publicBase . '/' . ltrim($heroFile, '/'));
    } elseif ($heroPath !== '') {
        $heroImg = asset('storage/' . ltrim($heroPath, '/'));
    } else {
        $heroImg = $heroUrl;
    }

    $heroAlt  = (string) data_get($cfg, 'hero.image.alt', 'About image');

    $heroFit  = (string) data_get($cfg, 'hero.image.fit', 'contain');
    if (!in_array($heroFit, ['cover', 'contain'], true)) {
        $heroFit = 'contain';
    }

    $heroZoom = (float) data_get($cfg, 'hero.image.zoom', 1);
    if ($heroZoom <= 0) {
        $heroZoom = 1;
    }
    $heroZoom = max(0.5, min(3.0, $heroZoom));

    $seoImg = $heroImg;

    $cta1 = data_get($cfg, 'hero.cta_primary', null);
    $cta2 = data_get($cfg, 'hero.cta_secondary', null);

    $cta1Enabled = (bool) data_get($cta1, 'enabled', true);
    $cta2Enabled = (bool) data_get($cta2, 'enabled', true);

    $statsEnabled = (bool) data_get($cfg, 'stats_enabled', true);
    $stats = (array) data_get($cfg, 'stats', []);

    $storyEnabled    = (bool) data_get($cfg, 'story.enabled', true);
    $valuesEnabled   = (bool) data_get($cfg, 'values.enabled', true);
    $teamEnabled     = (bool) data_get($cfg, 'team.enabled', true);
    $timelineEnabled = (bool) data_get($cfg, 'timeline.enabled', true);
    $galleryEnabled  = (bool) data_get($cfg, 'gallery.enabled', true);

    $title = $seoTitle;
    $description = $seoDesc;
    $image = $seoImg;

    $teamImgSrc = function (array $m) use ($publicBase): string {
        $file = (string) data_get($m, 'image_file', '');
        if ($file !== '') {
            return asset($publicBase . '/' . ltrim($file, '/'));
        }

        $p = (string) data_get($m, 'image_path', '');
        if ($p !== '') {
            return asset('storage/' . ltrim($p, '/'));
        }

        return (string) data_get($m, 'image', '');
    };

    $galleryImgSrc = function (array $g) use ($publicBase): string {
        $file = (string) data_get($g, 'file', '');
        if ($file !== '') {
            return asset($publicBase . '/' . ltrim($file, '/'));
        }

        $p = (string) data_get($g, 'path', '');
        if ($p !== '') {
            return asset('storage/' . ltrim($p, '/'));
        }

        return (string) data_get($g, 'url', '');
    };

    $fitSafe = function (mixed $fit): string {
        $fit = (string) $fit;
        return in_array($fit, ['cover', 'contain'], true) ? $fit : 'contain';
    };

    $zoomSafe = function (mixed $z): float {
        $z = (float) $z;
        if ($z <= 0) $z = 1;
        return max(0.5, min(3.0, $z));
    };

    $isAuthenticated = auth()->check();
    $authUser = auth()->user();

    ob_start();
@endphp

<style>
    .oa-wrap { max-width: 1200px; margin: 0 auto; padding: 28px 18px 80px; }
    .oa-hero { display:grid; grid-template-columns: 1.15fr 0.85fr; gap: 22px; align-items: center; }
    .oa-card { border:1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.04); border-radius: 22px; padding: 18px; }
    .oa-title { margin:0; font-size: 44px; line-height:1.05; font-weight: 950; color: #fff; letter-spacing: -0.02em; }
    .oa-sub { margin-top: 12px; color: rgba(255,255,255,0.70); font-size: 15px; line-height: 1.55; }
    .oa-actions { margin-top: 16px; display:flex; gap: 10px; flex-wrap: wrap; }
    .oa-btn { display:inline-flex; align-items:center; justify-content:center; border-radius: 16px; padding: 12px 14px; font-weight: 900; font-size: 13px; border:1px solid rgba(255,255,255,0.12); background: rgba(255,255,255,0.06); color:#fff; text-decoration:none; }
    .oa-btn:hover { background: rgba(255,255,255,0.10); }
    .oa-btn-primary { border-color: rgba(168,85,247,0.35); background: rgba(168,85,247,0.95); }
    .oa-btn-primary:hover { background: rgba(168,85,247,0.85); }

    .oa-img { width:100%; height: 360px; border-radius: 22px; overflow:hidden; border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.25); }
    .oa-img img { width:100%; height:100%; display:block; object-fit: contain; transform-origin: center; }

    .oa-grid { margin-top: 18px; display:grid; grid-template-columns: 1fr; gap: 18px; }
    .oa-section-title { margin:0; font-size: 18px; color:#fff; font-weight: 950; }
    .oa-muted { margin-top: 8px; color: rgba(255,255,255,0.60); font-size: 13px; line-height: 1.55; }

    .oa-stats { display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 14px; }
    .oa-stat { border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.22); border-radius: 18px; padding: 14px; }
    .oa-stat-val { font-size: 20px; font-weight: 950; color:#fff; }
    .oa-stat-lab { margin-top: 6px; font-size: 12px; color: rgba(255,255,255,0.55); font-weight: 800; letter-spacing: 0.05em; text-transform: uppercase; }

    .oa-values { display:grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-top: 12px; }
    .oa-value { border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.22); border-radius: 18px; padding: 14px; }
    .oa-value h4 { margin:0; color:#fff; font-weight: 950; font-size: 14px; }
    .oa-value p { margin:8px 0 0; color: rgba(255,255,255,0.60); font-size: 13px; line-height: 1.5; }

    .oa-team { display:grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-top: 12px; }
    .oa-person { border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.22); border-radius: 18px; padding: 14px; display:flex; gap: 12px; align-items:center; }
    .oa-avatar { width: 54px; height: 54px; border-radius: 16px; overflow:hidden; border:1px solid rgba(255,255,255,0.10); background: rgba(255,255,255,0.06); }
    .oa-avatar img { width:100%; height:100%; display:block; object-fit: contain; transform-origin: center; }
    .oa-person-name { color:#fff; font-weight: 950; }
    .oa-person-role { margin-top: 4px; color: rgba(255,255,255,0.55); font-size: 13px; font-weight: 800; }

    .oa-timeline { display:grid; grid-template-columns: 1fr; gap: 10px; margin-top: 12px; }
    .oa-milestone { border:1px solid rgba(255,255,255,0.10); background: rgba(0,0,0,0.22); border-radius: 18px; padding: 14px; display:flex; gap: 14px; }
    .oa-year { min-width: 70px; font-weight: 950; color: rgba(216,180,254,0.95); }
    .oa-mt-title { color:#fff; font-weight: 950; }
    .oa-mt-text { margin-top: 6px; color: rgba(255,255,255,0.60); font-size: 13px; line-height: 1.5; }

    .oa-gallery { display:grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-top: 12px; }
    .oa-gimg { border-radius: 18px; overflow:hidden; border:1px solid rgba(255,255,255,0.10); height: 140px; background: rgba(0,0,0,0.22); }
    .oa-gimg img { width:100%; height:100%; display:block; object-fit: contain; transform-origin: center; }

    @media (max-width: 980px) {
        .oa-hero { grid-template-columns: 1fr; }
        .oa-img { height: 300px; }
        .oa-stats { grid-template-columns: repeat(2, 1fr); }
        .oa-values { grid-template-columns: repeat(2, 1fr); }
        .oa-team { grid-template-columns: 1fr; }
        .oa-gallery { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<div class="oa-wrap">
    @if ($heroEnabled)
        <div class="oa-hero">
            <div class="oa-card">
                <h1 class="oa-title">{{ $heroTitle }}</h1>

                @if ($heroSub !== '')
                    <div class="oa-sub">{{ $heroSub }}</div>
                @endif

                <div class="oa-actions">
                    @if (is_array($cta1) && $cta1Enabled && !empty($cta1['label']) && !empty($cta1['url']))
                        <a class="oa-btn oa-btn-primary" href="{{ $cta1['url'] }}">{{ $cta1['label'] }}</a>
                    @endif

                    @if (is_array($cta2) && $cta2Enabled && !empty($cta2['label']) && !empty($cta2['url']))
                        <a class="oa-btn" href="{{ $cta2['url'] }}">{{ $cta2['label'] }}</a>
                    @endif
                </div>

                @if ($statsEnabled && !empty($stats))
                    <div class="oa-stats">
                        @foreach ($stats as $s)
                            @php
                                $v = (string) ($s['value'] ?? '');
                                $l = (string) ($s['label'] ?? '');
                            @endphp
                            @if ($v !== '' || $l !== '')
                                <div class="oa-stat">
                                    <div class="oa-stat-val">{{ $v }}</div>
                                    <div class="oa-stat-lab">{{ $l }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="oa-img">
                @if ($heroImg !== '')
                    <img
                        src="{{ $heroImg }}"
                        alt="{{ $heroAlt }}"
                        style="object-fit: contain; transform: scale(1);"
                    >
                @endif
            </div>
        </div>
    @endif

    <div class="oa-grid">
        @if ($storyEnabled && is_array(data_get($cfg, 'story', null)))
            <div class="oa-card">
                <div class="oa-section-title">{{ (string) data_get($cfg, 'story.title', 'Our story') }}</div>

                @php $paras = (array) data_get($cfg, 'story.paragraphs', []); @endphp
                @foreach ($paras as $p)
                    @if ((string) $p !== '')
                        <div class="oa-muted">{{ (string) $p }}</div>
                    @endif
                @endforeach
            </div>
        @endif

        @if ($valuesEnabled && is_array(data_get($cfg, 'values', null)))
            <div class="oa-card">
                <div class="oa-section-title">{{ (string) data_get($cfg, 'values.title', 'What we stand for') }}</div>

                <div class="oa-values">
                    @foreach ((array) data_get($cfg, 'values.items', []) as $it)
                        @php
                            $t = (string) ($it['title'] ?? '');
                            $x = (string) ($it['text'] ?? '');
                        @endphp
                        @if ($t !== '' || $x !== '')
                            <div class="oa-value">
                                <h4>{{ $t }}</h4>
                                <p>{{ $x }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($teamEnabled && is_array(data_get($cfg, 'team', null)))
            <div class="oa-card">
                <div class="oa-section-title">{{ (string) data_get($cfg, 'team.title', 'Meet the team') }}</div>

                <div class="oa-team">
                    @foreach ((array) data_get($cfg, 'team.members', []) as $m)
                        @php
                            $m = is_array($m) ? $m : [];
                            $nm = (string) data_get($m, 'name', '');
                            $rl = (string) data_get($m, 'role', '');
                            $im = $teamImgSrc($m);
                        @endphp
                        @if ($nm !== '' || $rl !== '' || $im !== '')
                            <div class="oa-person">
                                <div class="oa-avatar">
                                    @if ($im !== '')
                                        <img
                                            src="{{ $im }}"
                                            alt="{{ $nm }}"
                                            style="object-fit: contain; transform: scale(1);"
                                        >
                                    @endif
                                </div>
                                <div>
                                    <div class="oa-person-name">{{ $nm }}</div>
                                    <div class="oa-person-role">{{ $rl }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($timelineEnabled && is_array(data_get($cfg, 'timeline', null)))
            <div class="oa-card">
                <div class="oa-section-title">{{ (string) data_get($cfg, 'timeline.title', 'Milestones') }}</div>

                <div class="oa-timeline">
                    @foreach ((array) data_get($cfg, 'timeline.items', []) as $t)
                        @php
                            $yr = (string) ($t['year'] ?? '');
                            $tt = (string) ($t['title'] ?? '');
                            $tx = (string) ($t['text'] ?? '');
                        @endphp
                        @if ($yr !== '' || $tt !== '' || $tx !== '')
                            <div class="oa-milestone">
                                <div class="oa-year">{{ $yr }}</div>
                                <div>
                                    <div class="oa-mt-title">{{ $tt }}</div>
                                    <div class="oa-mt-text">{{ $tx }}</div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($galleryEnabled && is_array(data_get($cfg, 'gallery', null)))
            <div class="oa-card">
                <div class="oa-section-title">{{ (string) data_get($cfg, 'gallery.title', 'Behind the scenes') }}</div>

                <div class="oa-gallery">
                    @foreach ((array) data_get($cfg, 'gallery.images', []) as $g)
                        @php
                            $g = is_array($g) ? $g : [];
                            $imgSrc = $galleryImgSrc($g);
                            $a = (string) data_get($g, 'alt', '');
                        @endphp
                        @if ($imgSrc !== '')
                            <div class="oa-gimg">
                                <img
                                    src="{{ $imgSrc }}"
                                    alt="{{ $a }}"
                                    style="object-fit: contain; transform: scale(1);"
                                >
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@php
    $slot = ob_get_clean();
@endphp

@include('layouts.app', [
    'title' => $title,
    'description' => $description,
    'image' => $image,
    'slot' => $slot,
    'isAuthenticated' => $isAuthenticated,
    'authUser' => $authUser,
])
