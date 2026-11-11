@props([
    'title' => '',
    'closable' => true,
    'closeTrigger' => '',
    'open' => false,
    'width' => 'max-w-3xl',
    'variant' => 'default'
])
<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" class="fc-modal-shell">
    <template x-teleport="body">
        <div class="fixed inset-0 z-30 flex items-center justify-center overflow-hidden bg-black/50 fc-modal-backdrop fc-modal-backdrop--{{ $variant }}"
            x-show="open">
            <!-- Modal inner -->
            <div class="px-6 py-4 w-full mx-2 md:mx-auto text-left bg-background-secondary rounded shadow-lg max-h-[calc(100%-theme('spacing.8'))] overflow-y-auto mb-8 mt-8 {{ $width }} fc-modal-panel fc-modal-panel--{{ $variant }}" x-cloak
                x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-90"
                x-transition:enter-end="opacity-100 scale-100" x-transition:leave="transition ease-in duration-300"
                x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-90">
                <div class="flex justify-between items-center">
                    <h2 class="text-2xl font-semibold text-primary-100 fc-modal-panel__title">{{ $title }}</h2>
                    @if ($closable && !$closeTrigger)
                        <button @click="open = false" class="text-primary-100">
                            <x-ri-close-fill class="size-6" />
                        </button>
                    @elseif ($closable && $closeTrigger)
                        {{ $closeTrigger }}
                    @endif
                </div>
                <div class="mt-4">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </template>

</div>
