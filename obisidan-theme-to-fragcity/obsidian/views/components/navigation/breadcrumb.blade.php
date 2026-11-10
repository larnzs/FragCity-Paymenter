{{-- themes/obsidian/views/components/navigation/breadcrumb.blade.php --}}

@php
    $currentRoute = request()->livewireUrl();

    $navigation = [
        \App\Classes\Navigation::getLinks(),
        \App\Classes\Navigation::getAccountDropdownLinks(),
        \App\Classes\Navigation::getDashboardLinks(),
    ];

    function findBreadcrumb($items, $currentRoute) {
        foreach ($items as $item) {
            if (isset($item['url']) && $item['url'] === $currentRoute) {
                return [$item];
            }

            if (!empty($item['children'])) {
                $childTrail = findBreadcrumb($item['children'], $currentRoute);
                if (!empty($childTrail)) {
                    return array_merge([$item], $childTrail);
                }
            }
        }

        return [];
    }

    $breadcrumbs = [];
    foreach ($navigation as $group) {
        $breadcrumbs = findBreadcrumb($group, $currentRoute);
        if (!empty($breadcrumbs)) {
            break;
        }
    }

    $fallbackTitle = __('navigation.home');
@endphp

<div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
    @if (!empty($breadcrumbs))
        @foreach ($breadcrumbs as $index => $breadcrumb)
            @php
                $isLast = $index === count($breadcrumbs) - 1;

                $href = $breadcrumb['url'] ?? (
                    isset($breadcrumb['route'])
                        ? route($breadcrumb['route'], $breadcrumb['params'] ?? [])
                        : '#'
                );

                $label = $breadcrumb['name'] ?? '';
            @endphp

            @if ($index > 0)
                <x-ri-arrow-right-s-line class="size-4 text-base/40" />
            @endif

            @if ($isLast)
                {{-- Final crumb: subtle label (NOT a page title) --}}
                <span class="font-medium text-base/70">
                    {{ $label }}
                </span>
            @else
                <a
                    href="{{ $href }}"
                    @if($href !== '#') wire:navigate @endif
                    class="font-medium text-base/50 hover:text-base/80 transition"
                >
                    {{ $label }}
                </a>
            @endif
        @endforeach
    @else
        <span class="font-medium text-base/70">
            {{ $fallbackTitle }}
        </span>
    @endif
</div>
