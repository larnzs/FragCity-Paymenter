@php
    use Illuminate\Support\Facades\Cache;
    use Illuminate\Support\Facades\Http;

    // Section toggle
    $enabled = (bool) theme('video_hub_enabled', true);
    if (!$enabled) return;

    // Section text
    $title = trim((string) theme('video_hub_title', 'Video Hub'));
    $subtitle = trim((string) theme('video_hub_subtitle', 'Tutorials, walkthroughs, and quick tips to get the most out of your services.'));

    // API Key (required for pulling playlist items)
    $apiKey = trim((string) theme('video_hub_youtube_api_key', ''));

    // Optional: channel id (not required for playlists)
    $channelId = trim((string) theme('video_hub_channel_id', ''));

    // Helpers
    $parsePlaylistId = function (?string $value): string {
        $v = trim((string) $value);
        if ($v === '') return '';

        // If they paste a full URL, try to extract list=
        if (str_contains($v, 'list=')) {
            $parts = parse_url($v);
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $q);
                if (!empty($q['list'])) return trim((string) $q['list']);
            }
        }

        // Raw playlist id
        return $v;
    };

    $safeText = function (string $t): string {
        return trim(preg_replace('/\s+/', ' ', $t));
    };

    // Pull categories from YOUR theme.php keys (with backward-compat fallback)
    $rawCategories = collect([
        [
            'key'   => 'cat1',
            'title' => (string) theme('video_hub_category_1_title', theme('video_cat_1_title', 'Getting Started')),
            'pid'   => (string) theme('video_hub_category_1_playlist_id', theme('video_cat_1_playlist', '')),
        ],
        [
            'key'   => 'cat2',
            'title' => (string) theme('video_hub_category_2_title', theme('video_cat_2_title', 'Game Servers')),
            'pid'   => (string) theme('video_hub_category_2_playlist_id', theme('video_cat_2_playlist', '')),
        ],
        [
            'key'   => 'cat3',
            'title' => (string) theme('video_hub_category_3_title', theme('video_cat_3_title', 'VPS & Networking')),
            'pid'   => (string) theme('video_hub_category_3_playlist_id', theme('video_cat_3_playlist', '')),
        ],
        [
            'key'   => 'cat4',
            'title' => (string) theme('video_hub_category_4_title', theme('video_cat_4_title', 'Web Hosting')),
            'pid'   => (string) theme('video_hub_category_4_playlist_id', theme('video_cat_4_playlist', '')),
        ],
    ])->map(function ($c) use ($parsePlaylistId, $safeText) {
        $c['title'] = $safeText((string) $c['title']);
        $c['playlistId'] = $parsePlaylistId((string) $c['pid']);
        return $c;
    })->filter(fn ($c) => $c['playlistId'] !== '' && $c['title'] !== '')->values();

    // If no API key or no playlists configured, show a clean placeholder
    $hasPlaylists = $rawCategories->count() > 0;

    // How many videos per playlist to show on homepage
    $perPlaylist = 8;

    // Fetch playlist items (cached)
    $fetchPlaylistVideos = function (string $playlistId) use ($apiKey, $perPlaylist) {
        if ($apiKey === '') return collect();

        $cacheKey = "obsidian:videoHub:playlist:" . $playlistId . ":limit:" . $perPlaylist;

        return Cache::remember($cacheKey, now()->addHours(6), function () use ($apiKey, $playlistId, $perPlaylist) {
            $res = Http::timeout(8)->get('https://www.googleapis.com/youtube/v3/playlistItems', [
                'part'       => 'snippet',
                'maxResults' => $perPlaylist,
                'playlistId' => $playlistId,
                'key'        => $apiKey,
            ]);

            if (!$res->ok()) return collect();

            $json = $res->json();
            $items = collect($json['items'] ?? []);

            return $items->map(function ($it) {
                $sn = $it['snippet'] ?? [];
                $vid = $sn['resourceId']['videoId'] ?? null;
                if (!$vid) return null;

                $thumbs = $sn['thumbnails'] ?? [];
                $thumb =
                    ($thumbs['maxres']['url'] ?? null) ? $thumbs['maxres']['url'] :
                    (($thumbs['high']['url'] ?? null) ? $thumbs['high']['url'] :
                    (($thumbs['medium']['url'] ?? null) ? $thumbs['medium']['url'] :
                    ($thumbs['default']['url'] ?? '')));

                return [
                    'videoId'     => $vid,
                    'title'       => (string) ($sn['title'] ?? ''),
                    'publishedAt' => (string) ($sn['publishedAt'] ?? ''),
                    'thumb'       => (string) $thumb,
                    'url'         => 'https://www.youtube.com/watch?v=' . $vid,
                ];
            })->filter()->values();
        });
    };

    // Build data for UI
    $categories = $rawCategories->map(function ($cat) use ($fetchPlaylistVideos) {
        $cat['videos'] = $fetchPlaylistVideos($cat['playlistId']);
        return $cat;
    })->values();

    $allVideos = $categories->flatMap(function ($cat) {
        return $cat['videos']->map(function ($v) use ($cat) {
            $v['categoryKey'] = $cat['key'];
            $v['categoryTitle'] = $cat['title'];
            return $v;
        });
    })->values();

    // Optional “View all” link (defaults to channel if provided)
    $viewAllUrl = '';
    if ($channelId !== '') {
        $viewAllUrl = 'https://www.youtube.com/channel/' . $channelId;
    } else {
        // fallback: first playlist
        $firstPid = $categories->first()['playlistId'] ?? '';
        if ($firstPid !== '') {
            $viewAllUrl = 'https://www.youtube.com/playlist?list=' . $firstPid;
        }
    }
@endphp

<section class="relative py-28 md:py-32">
    <div class="container">
        {{-- Header --}}
        <div class="mx-auto max-w-[980px] text-center">
            @if($title !== '')
                <h2 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    {{ $title }}
                </h2>
            @endif

            @if($subtitle !== '')
                <p class="mx-auto mt-4 max-w-[720px] text-sm text-base/60 sm:text-base">
                    {{ $subtitle }}
                </p>
            @endif
        </div>

        {{-- If missing setup, show a clean message --}}
        @if(!$hasPlaylists || $apiKey === '')
            <div class="mx-auto mt-12 max-w-[980px] overflow-hidden rounded-2xl border border-neutral/50 bg-background-secondary/40 p-10 text-center">
                <h3 class="text-lg font-semibold">Video Hub not configured yet</h3>
                <p class="mt-2 text-sm text-base/60">
                    @if($apiKey === '')
                        Add your <span class="font-semibold">YouTube API Key</span> in Theme Settings.
                    @else
                        Add at least one <span class="font-semibold">Playlist ID</span> in Theme Settings.
                    @endif
                </p>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @for($i=1; $i<=4; $i++)
                        <div class="rounded-xl border border-neutral/50 bg-background-secondary/40 p-4 text-left">
                            <div class="text-sm font-semibold">Category {{ $i }}</div>
                            <div class="mt-1 text-xs text-base/60">Setting:</div>
                            <div class="mt-1 font-mono text-xs text-base/70">video_hub_category_{{ $i }}_playlist_id</div>
                            <div class="mt-2 text-xs text-base/60">
                                Paste a playlist ID like <span class="font-mono">PLxxxx</span> (or a URL with <span class="font-mono">list=PLxxxx</span>)
                            </div>
                        </div>
                    @endfor
                </div>
            </div>
            @php return; @endphp
        @endif

        {{-- Tabs --}}
        <div class="mx-auto mt-10 flex max-w-[980px] flex-wrap items-center justify-center gap-3">
            <button
                type="button"
                class="videoHubTab inline-flex items-center justify-center rounded-full border border-neutral/60 bg-background-secondary/40 px-5 py-2.5 text-sm font-semibold text-base transition hover:bg-background-secondary/65"
                data-target="all"
            >
                All Videos
            </button>

            @foreach($categories as $cat)
                <button
                    type="button"
                    class="videoHubTab inline-flex items-center justify-center rounded-full border border-neutral/60 bg-background-secondary/40 px-5 py-2.5 text-sm font-semibold text-base transition hover:bg-background-secondary/65"
                    data-target="{{ $cat['key'] }}"
                >
                    {{ $cat['title'] }}
                </button>
            @endforeach
        </div>

        {{-- Grid --}}
        <div class="mx-auto mt-12 max-w-[1200px]">
            <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
                @foreach($allVideos as $v)
                    @php
                        $published = $v['publishedAt'] ? \Carbon\Carbon::parse($v['publishedAt'])->format('d/m/Y') : '';
                        $titleText = trim((string) $v['title']);
                        $titleText = $titleText !== '' ? $titleText : 'Untitled Video';
                    @endphp

                    <a
                        href="{{ $v['url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="videoHubCard group overflow-hidden rounded-2xl border border-neutral/50 bg-background-secondary/40 transition hover:bg-background-secondary/55"
                        data-cat="{{ $v['categoryKey'] }}"
                        title="{{ $titleText }}"
                    >
                        <div class="aspect-[16/9] w-full overflow-hidden bg-background-secondary/55">
                            @if($v['thumb'] !== '')
                                <img
                                    src="{{ $v['thumb'] }}"
                                    alt="{{ $titleText }}"
                                    class="h-full w-full object-cover transition group-hover:scale-[1.02]"
                                    loading="lazy"
                                >
                            @endif
                        </div>

                        <div class="p-5">
                            <div class="text-xs font-semibold tracking-[0.14em] uppercase text-base/55">
                                {{ $v['categoryTitle'] }}
                            </div>

                            <div class="mt-2 line-clamp-2 text-sm font-semibold text-base">
                                {{ $titleText }}
                            </div>

                            <div class="mt-3 flex items-center justify-between text-xs text-base/60">
                                <span>{{ $published }}</span>
                                <span class="inline-flex items-center justify-center rounded-full border border-neutral/60 bg-background-secondary/40 px-3 py-1 text-xs font-semibold">
                                    Watch
                                </span>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            @if($viewAllUrl !== '')
                <div class="mt-12 flex justify-center">
                    <a
                        href="{{ $viewAllUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center justify-center rounded-full bg-base px-12 py-4 text-sm font-semibold text-background transition hover:bg-base/90"
                    >
                        View All Videos
                    </a>
                </div>
            @endif
        </div>
    </div>

    {{-- Tiny JS for tabs --}}
    <script>
        (function () {
            const tabs = Array.from(document.querySelectorAll('.videoHubTab'));
            const cards = Array.from(document.querySelectorAll('.videoHubCard'));

            function setActive(tab) {
                tabs.forEach(t => {
                    t.classList.remove('bg-base', 'text-background', 'border-base');
                    t.classList.add('border-neutral/60', 'bg-background-secondary/40', 'text-base');
                });

                tab.classList.remove('border-neutral/60', 'bg-background-secondary/40', 'text-base');
                tab.classList.add('bg-base', 'text-background', 'border-base');
            }

            function filter(target) {
                cards.forEach(c => {
                    const cat = c.getAttribute('data-cat');
                    c.style.display = (target === 'all' || target === cat) ? '' : 'none';
                });
            }

            // Default: All Videos active
            const first = tabs[0];
            if (first) {
                setActive(first);
                filter(first.getAttribute('data-target') || 'all');
            }

            tabs.forEach(tab => {
                tab.addEventListener('click', () => {
                    const target = tab.getAttribute('data-target') || 'all';
                    setActive(tab);
                    filter(target);
                });
            });
        })();
    </script>
</section>
