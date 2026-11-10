{{-- themes/obsidian/views/components/navigation/index.blade.php --}}

<nav
    x-data="{
        mobileOpen: false,
        desktopServicesOpen: false
    }"
    class="fixed top-0 z-30 w-full border-b border-neutral bg-background/80 backdrop-blur"
>
    <div class="container h-16 relative flex items-center justify-between">
        <div class="flex items-center gap-2">
            <a href="{{ route('home') }}" wire:navigate class="flex items-center gap-2">
                <x-logo class="h-8 w-8" />
                <span class="text-lg font-semibold tracking-tight">
                    {{ config('app.name') }}
                </span>
            </a>

            @php
                // Load navbar options
                $rawOpts = \App\Models\Setting::where('key', 'obsidian.navbar.options')->value('value');
                $opts = is_string($rawOpts) ? json_decode($rawOpts, true) : null;
                if (!is_array($opts)) $opts = [];

                $themeToggleEnabled = (bool) data_get($opts, 'theme_toggle.enabled', false);
                $themeToggleShowLabel = (bool) data_get($opts, 'theme_toggle.show_label', false);
                $themeToggleLabel = (string) data_get($opts, 'theme_toggle.label', 'Theme');
                $themeTogglePosition = (string) data_get($opts, 'theme_toggle.position', 'right');

                if ($themeToggleLabel === '') $themeToggleLabel = 'Theme';
                if (!in_array($themeTogglePosition, ['left', 'right'], true)) $themeTogglePosition = 'right';
            @endphp

            {{-- Theme toggle button (left position) --}}
            @if ($themeToggleEnabled && $themeTogglePosition === 'left')
                <button
                    type="button"
                    @click="darkMode = !darkMode"
                    class="hidden sm:inline-flex items-center justify-center rounded-lg border border-neutral bg-background hover:bg-background-secondary transition h-10 px-3 ml-2"
                    aria-label="Toggle theme"
                    title="Toggle theme"
                >
                    <span class="flex items-center gap-2">
                        {{-- Moon icon when darkMode is true --}}
                        <svg x-show="darkMode" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        {{-- Sun icon when darkMode is false --}}
                        <svg x-show="!darkMode" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>

                        @if ($themeToggleShowLabel)
                            <span class="text-sm font-medium">{{ $themeToggleLabel }}</span>
                        @endif
                    </span>
                </button>
            @endif
        </div>

        @php
            $raw = \App\Models\Setting::where('key', 'obsidian.navbar')->value('value');
            $decoded = is_string($raw) ? json_decode($raw, true) : null;

            $default = [
                [
                    'label' => 'Services',
                    'type' => 'dropdown',
                    'url' => '',
                    'visibility' => 'always',
                    'enabled' => true,
                    'children' => [
                        ['label' => 'Game Servers', 'type' => 'internal', 'url' => '/game-servers', 'visibility' => 'always', 'enabled' => true],
                        ['label' => 'VPS', 'type' => 'internal', 'url' => '/vps', 'visibility' => 'always', 'enabled' => true],
                        ['label' => 'Web Hosting', 'type' => 'internal', 'url' => '/web-hosting', 'visibility' => 'always', 'enabled' => true],
                        ['label' => 'Dedicated Servers', 'type' => 'internal', 'url' => '/dedicated', 'visibility' => 'always', 'enabled' => true],
                    ],
                ],
                ['label' => 'Pricing', 'type' => 'internal', 'url' => '/pricing', 'visibility' => 'always', 'enabled' => true],
                ['label' => 'Status', 'type' => 'internal', 'url' => '/status', 'visibility' => 'always', 'enabled' => true],
                ['label' => 'Docs', 'type' => 'internal', 'url' => '/docs', 'visibility' => 'always', 'enabled' => true],
                ['label' => 'Support', 'type' => 'internal', 'url' => '/support', 'visibility' => 'always', 'enabled' => true],
            ];

            $navItems = is_array($decoded) ? $decoded : $default;

            $isAuth = auth()->check();

            $visible = function ($node) use ($isAuth) {
                if (!is_array($node)) return false;
                if (isset($node['enabled']) && $node['enabled'] === false) return false;

                $vis = $node['visibility'] ?? 'always';
                if ($vis === 'always') return true;
                if ($vis === 'guest') return !$isAuth;
                if ($vis === 'auth') return $isAuth;

                return true;
            };

            $hrefFor = function ($node) {
                $type = $node['type'] ?? 'internal';
                $url = $node['url'] ?? '#';

                if ($type === 'external') return $url;
                return $url;
            };
        @endphp

        <div class="hidden lg:flex items-center gap-8 absolute left-1/2 -translate-x-1/2">
            @foreach ($navItems as $item)
                @continue(!$visible($item))

                @php $type = $item['type'] ?? 'internal'; @endphp

                @if ($type === 'dropdown')
                    @php
                        $children = $item['children'] ?? [];
                        if (!is_array($children)) $children = [];
                        $children = array_values(array_filter($children, fn($c) => $visible($c)));
                    @endphp

                    @if (count($children) > 0)
                        <div
                            class="relative"
                            x-data="{ open: false }"
                            @mouseenter="open = true"
                            @mouseleave="
                                setTimeout(() => {
                                    if (
                                        !$refs.trigger.matches(':hover') &&
                                        !$refs.dropdown.matches(':hover')
                                    ) {
                                        open = false
                                    }
                                }, 120)
                            "
                        >
                            <button
                                x-ref="trigger"
                                type="button"
                                class="flex items-center gap-1 text-base text-base/80 hover:text-base transition-colors"
                            >
                                {{ $item['label'] ?? 'Menu' }}
                                <svg class="w-4 h-4 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div
                                x-ref="dropdown"
                                x-show="open"
                                x-cloak
                                class="absolute top-full left-1/2 -translate-x-1/2 mt-3 w-56 rounded-xl border border-neutral bg-background-secondary shadow-xl"
                            >
                                <div class="p-2 flex flex-col gap-1">
                                    @foreach ($children as $child)
                                        @php
                                            $childHref = $hrefFor($child);
                                            $childExternal = (($child['type'] ?? 'internal') === 'external');
                                        @endphp

                                        <a
                                            href="{{ $childHref }}"
                                            @if($childExternal) target="_blank" rel="noopener noreferrer" @endif
                                            class="px-3 py-2 rounded-lg text-sm hover:bg-background-secondary/70"
                                        >
                                            {{ $child['label'] ?? 'Link' }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif
                @else
                    @php
                        $href = $hrefFor($item);
                        $external = (($item['type'] ?? 'internal') === 'external');
                    @endphp

                    <a
                        href="{{ $href }}"
                        @if($external) target="_blank" rel="noopener noreferrer" @endif
                        class="text-base text-base/80 hover:text-base transition-colors"
                    >
                        {{ $item['label'] ?? 'Link' }}
                    </a>
                @endif
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            {{-- Theme toggle button (right position) --}}
            @if ($themeToggleEnabled && $themeTogglePosition === 'right')
                <button
                    type="button"
                    @click="darkMode = !darkMode"
                    class="hidden sm:inline-flex items-center justify-center rounded-lg border border-neutral bg-background hover:bg-background-secondary transition h-10 px-3"
                    aria-label="Toggle theme"
                    title="Toggle theme"
                >
                    <span class="flex items-center gap-2">
                        <svg x-show="darkMode" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                        <svg x-show="!darkMode" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>

                        @if ($themeToggleShowLabel)
                            <span class="text-sm font-medium">{{ $themeToggleLabel }}</span>
                        @endif
                    </span>
                </button>
            @endif

            @guest
                <a href="{{ route('login') }}" wire:navigate class="text-sm font-medium text-base/80 hover:text-base">
                    Login
                </a>

                <a href="/deploy">
                    <x-button.primary class="px-5">
                        Deploy Now
                    </x-button.primary>
                </a>
            @endguest

            @auth
                <livewire:components.notifications />

                <x-dropdown :showArrow="false">
                    <x-slot:trigger>
                        <img
                            src="{{ auth()->user()->avatar }}"
                            class="size-8 rounded-full border border-neutral bg-background"
                            alt="avatar"
                        />
                    </x-slot:trigger>

                    <x-slot:content>
                        <div class="p-3">
                            <div class="text-sm font-medium">{{ auth()->user()->name }}</div>
                            <div class="text-xs text-base/60">{{ auth()->user()->email }}</div>
                        </div>

                        @foreach (\App\Classes\Navigation::getAccountDropdownLinks() as $nav)
                            <x-navigation.link
                                :href="$nav['url']"
                                :spa="isset($nav['spa']) ? $nav['spa'] : true"
                            >
                                {{ $nav['name'] }}
                            </x-navigation.link>
                        @endforeach

                        <livewire:auth.logout />
                    </x-slot:content>
                </x-dropdown>
            @endauth

            <button
                @click="mobileOpen = !mobileOpen"
                class="flex lg:hidden size-10 items-center justify-center rounded-lg hover:bg-background-secondary"
                aria-label="Toggle Menu"
            >
                <x-ri-menu-fill class="size-5" />
            </button>
        </div>
    </div>

    {{-- Mobile menu (fixed overlay + backdrop + panel under the navbar bar) --}}
    <div
        x-show="mobileOpen"
        x-cloak
        class="lg:hidden fixed inset-0 z-40"
        role="dialog"
        aria-modal="true"
        @keydown.escape.window="mobileOpen = false"
    >
        {{-- Backdrop (starts under the navbar, so the top bar stays clean) --}}
        <div
            class="absolute inset-0 top-16 bg-black/55"
            @click="mobileOpen = false"
            aria-hidden="true"
        ></div>

        {{-- Panel --}}
        <div class="absolute left-0 right-0 top-16 border-b border-neutral bg-background-secondary/95 backdrop-blur">
            <div class="container py-6 flex flex-col gap-4 max-h-[calc(100vh-4rem)] overflow-y-auto">
                <x-navigation.sidebar-links />

                @guest
                    <a href="{{ route('login') }}" wire:navigate @click="mobileOpen = false">
                        <x-button.secondary class="w-full">
                            Login
                        </x-button.secondary>
                    </a>

                    <a href="/deploy" @click="mobileOpen = false">
                        <x-button.primary class="w-full">
                            Deploy Now
                        </x-button.primary>
                    </a>
                @endguest
            </div>
        </div>
    </div>
</nav>
