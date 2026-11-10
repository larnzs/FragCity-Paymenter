<div class="space-y-6">

    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-2xl font-semibold text-white">Navbar</div>
            <div class="mt-1 text-sm text-white/60">
                Manage the Obsidian theme navbar buttons. Styling stays in theme settings.
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="saveAll"
                class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white hover:bg-white/10"
            >
                Save
            </button>

            <div class="relative">
                <button
                    type="button"
                    wire:click="openNewLinkModal"
                    class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-500"
                >
                    New Navbar Link
                </button>
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="flex items-center justify-between gap-4">
            <div class="text-sm font-medium text-white">Preview</div>

            <div class="inline-flex rounded-xl border border-white/10 bg-black/20 p-1">
                <button
                    type="button"
                    wire:click="setPreviewMode('guest')"
                    class="rounded-lg px-3 py-1 text-xs font-medium {{ $previewMode === 'guest' ? 'bg-purple-600 text-white' : 'text-white/70 hover:text-white' }}"
                >
                    Guest
                </button>
                <button
                    type="button"
                    wire:click="setPreviewMode('auth')"
                    class="rounded-lg px-3 py-1 text-xs font-medium {{ $previewMode === 'auth' ? 'bg-purple-600 text-white' : 'text-white/70 hover:text-white' }}"
                >
                    Auth
                </button>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-white/10 bg-black/20 p-4">
            <div class="flex flex-wrap gap-3">
                @foreach ($previewItems as $p)
                    @if (($p['type'] ?? '') === 'dropdown')
                        <div class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-white/90">
                            <span>{{ $p['label'] ?? 'Dropdown' }}</span>
                            <span class="text-white/50">v</span>
                        </div>
                    @else
                        <div class="inline-flex items-center rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white/90">
                            {{ $p['label'] ?? 'Link' }}
                        </div>
                    @endif
                @endforeach

                @if (count($previewItems) === 0)
                    <div class="text-sm text-white/60">Nothing to preview.</div>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 p-6">
        <div class="text-sm font-medium text-white">Search</div>
        <div class="mt-3">
            <input
                type="text"
                wire:model.live="search"
                placeholder="Search by label or URL"
                class="w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-white/20 focus:outline-none"
            />
        </div>
        <div class="mt-2 text-xs text-white/50">
            Tip: Use New Navbar Link to add links. Edit opens a simple modal.
        </div>
    </div>

    <div class="rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
        <div class="px-6 py-4 text-sm font-medium text-white">Navigation Links</div>

        <div class="border-t border-white/10">
            <div class="grid grid-cols-12 gap-4 px-6 py-3 text-xs font-semibold tracking-wide text-white/40">
                <div class="col-span-3">Label</div>
                <div class="col-span-2">Type</div>
                <div class="col-span-3">URL</div>
                <div class="col-span-2">Order</div>
                <div class="col-span-1">Enabled</div>
                <div class="col-span-1 text-right">Actions</div>
            </div>

            <div class="divide-y divide-white/5">
                @forelse ($filteredItems as $index => $item)
                    @php
                        $type = $item['type'] ?? 'internal';
                        $enabled = (bool)($item['enabled'] ?? true);
                        $vis = $item['visibility'] ?? 'always';
                        $url = $item['url'] ?? '';
                    @endphp

                    <div class="grid grid-cols-12 gap-4 px-6 py-4">
                        <div class="col-span-3">
                            <div class="text-sm font-medium text-white">{{ $item['label'] ?? '' }}</div>
                            <div class="mt-1 text-xs text-white/40">Show: {{ $vis }}</div>
                        </div>

                        <div class="col-span-2">
                            @if ($type === 'external')
                                <span class="inline-flex rounded-full border border-purple-500/30 bg-purple-500/10 px-3 py-1 text-xs text-purple-200">External</span>
                            @elseif ($type === 'dropdown')
                                <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/70">Dropdown</span>
                            @else
                                <span class="inline-flex rounded-full border border-blue-500/30 bg-blue-500/10 px-3 py-1 text-xs text-blue-200">Internal</span>
                            @endif
                        </div>

                        <div class="col-span-3">
                            <div class="text-sm text-white/80">
                                @if ($type === 'dropdown')
                                    -
                                @else
                                    {{ $url }}
                                @endif
                            </div>
                        </div>

                        <div class="col-span-2">
                            <div class="flex items-center gap-2">
                                <button type="button" wire:click="moveUp({{ $index }})"
                                    class="rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs text-white hover:bg-white/10">Up</button>
                                <button type="button" wire:click="moveDown({{ $index }})"
                                    class="rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs text-white hover:bg-white/10">Down</button>
                            </div>
                        </div>

                        <div class="col-span-1">
                            @if ($enabled)
                                <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs text-emerald-200">Enabled</span>
                            @else
                                <span class="inline-flex rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-white/50">Disabled</span>
                            @endif
                        </div>

                        <div class="col-span-1 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <button type="button" wire:click="openEditModal({{ $index }})"
                                    class="text-sm font-medium text-purple-300 hover:text-purple-200">Edit</button>
                                <button type="button" wire:click="deleteItem({{ $index }})"
                                    class="text-sm font-medium text-red-400 hover:text-red-300">Delete</button>
                            </div>
                        </div>

                        @if (($item['type'] ?? '') === 'dropdown')
                            <div class="col-span-12 mt-2 rounded-xl border border-white/10 bg-black/20 p-4">
                                <div class="flex items-center justify-between">
                                    <div class="text-xs font-semibold text-white/60">
                                        Dropdown items: {{ is_array($item['children'] ?? null) ? count($item['children']) : 0 }}
                                    </div>
                                    <button type="button" wire:click="openNewChildModal({{ $index }})"
                                        class="rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs text-white hover:bg-white/10">
                                        Add dropdown item
                                    </button>
                                </div>

                                <div class="mt-3 space-y-2">
                                    @php
                                        $children = $item['children'] ?? [];
                                        if (!is_array($children)) { $children = []; }
                                    @endphp

                                    @forelse ($children as $childIndex => $child)
                                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3">
                                            <div class="min-w-0">
                                                <div class="text-sm font-medium text-white">{{ $child['label'] ?? '' }}</div>
                                                <div class="text-xs text-white/50">
                                                    {{ ($child['url'] ?? '') }} - Show: {{ ($child['visibility'] ?? 'always') }} - {{ (bool)($child['enabled'] ?? true) ? 'Enabled' : 'Disabled' }}
                                                </div>
                                            </div>

                                            <div class="flex items-center gap-2">
                                                <button type="button" wire:click="moveChildUp({{ $index }}, {{ $childIndex }})"
                                                    class="rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs text-white hover:bg-white/10">Up</button>
                                                <button type="button" wire:click="moveChildDown({{ $index }}, {{ $childIndex }})"
                                                    class="rounded-lg border border-white/10 bg-white/5 px-3 py-1 text-xs text-white hover:bg-white/10">Down</button>
                                                <button type="button" wire:click="openEditChildModal({{ $index }}, {{ $childIndex }})"
                                                    class="text-xs font-medium text-purple-300 hover:text-purple-200">Edit</button>
                                                <button type="button" wire:click="deleteChild({{ $index }}, {{ $childIndex }})"
                                                    class="text-xs font-medium text-red-400 hover:text-red-300">Delete</button>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-sm text-white/50">No dropdown items.</div>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-6 py-8 text-sm text-white/60">No links found.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($modalOpen)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/70" wire:click="closeModal"></div>

            <div class="relative w-full max-w-lg rounded-2xl border border-white/10 bg-[#0b0b0b] p-6">
                <div class="text-lg font-semibold text-white">
                    {{ $modalIsChild ? 'Dropdown Item' : 'Navbar Link' }}
                </div>

                <div class="mt-4 space-y-4">
                    <div>
                        <div class="text-xs font-semibold text-white/60">Label</div>
                        <input type="text" wire:model="fieldLabel"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-white/20 focus:outline-none"
                            placeholder="Example: Pricing" />
                    </div>

                    <div>
                        <div class="text-xs font-semibold text-white/60">Type</div>
                        <select wire:model="fieldType"
                            class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white focus:border-white/20 focus:outline-none">
                            <option value="internal">Internal</option>
                            <option value="external">External</option>
                            @if (!$modalIsChild)
                                <option value="dropdown">Dropdown</option>
                            @endif
                        </select>
                    </div>

                    @if ($fieldType !== 'dropdown')
                        <div>
                            <div class="text-xs font-semibold text-white/60">URL</div>
                            <input type="text" wire:model="fieldUrl"
                                class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white placeholder:text-white/40 focus:border-white/20 focus:outline-none"
                                placeholder="/pricing or https://example.com" />
                            <div class="mt-1 text-xs text-white/40">
                                Internal must start with /. External must start with http:// or https://
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="text-xs font-semibold text-white/60">Show for</div>
                            <select wire:model="fieldVisibility"
                                class="mt-2 w-full rounded-xl border border-white/10 bg-black/20 px-4 py-3 text-sm text-white focus:border-white/20 focus:outline-none">
                                <option value="always">Everyone</option>
                                <option value="guest">Guest</option>
                                <option value="auth">Auth</option>
                            </select>
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-3 text-sm text-white/80">
                                <input type="checkbox" wire:model="fieldEnabled"
                                    class="h-4 w-4 rounded border-white/20 bg-black/20" />
                                Enabled
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" wire:click="closeModal"
                        class="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm text-white hover:bg-white/10">
                        Cancel
                    </button>
                    <button type="button" wire:click="saveModal"
                        class="rounded-xl bg-purple-600 px-4 py-2 text-sm font-medium text-white hover:bg-purple-500">
                        Save
                    </button>
                </div>
            </div>
        </div>
    @endif

</div>
