<div>
    <div class="one-header">
        <div>
            <h1 class="one-title">Navbar</h1>
            <div class="one-sub">Edit, reorder, and manage dropdown children. Saves to setting key: obsidian.navbar</div>
        </div>

        <div class="one-actions">
            <button type="button" class="one-btn one-btn-primary" wire:click="openNew">Add link</button>
            <button type="button" class="one-btn" wire:click="saveAll">Save</button>
        </div>
    </div>

    @php
        $statusText = '';
        $sess = session('status');
        if (is_string($sess) && $sess !== '') {
            $statusText = $sess;
        }
    @endphp

    @if ($statusText !== '')
        <div class="one-card" style="padding:12px 14px; margin-bottom:18px;">
            <div class="one-muted" style="color:#fff;">{{ $statusText }}</div>
        </div>
    @endif

    <div class="one-grid">
        <div class="one-card">
            <div class="one-card-title-row">
                <div class="one-card-title">Preview</div>

                <div class="one-toggle">
                    <button type="button" wire:click="setPreviewAs('guest')" class="@if (($previewMode ?? 'guest') === 'guest') one-active @endif">Guest</button>
                    <button type="button" wire:click="setPreviewAs('auth')" class="@if (($previewMode ?? 'guest') === 'auth') one-active @endif">Auth</button>
                </div>
            </div>

            <div class="one-preview-box">
                @php $preview = $this->previewLinks(); @endphp

                @if (count($preview) === 0)
                    <div class="one-muted">Nothing enabled.</div>
                @else
                    <div class="one-pill-row">
                        @foreach ($preview as $p)
                            <div class="one-pill">{{ (string) ($p['label'] ?? '') }}</div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="one-card">
            <div class="one-card-title">Search</div>

            <div class="one-search-box">
                <input
                    type="text"
                    wire:model.debounce.250ms="search"
                    placeholder="Search by label or URL"
                />
            </div>

            <div class="one-hint">Use Add link to create buttons. Children opens dropdown child manager.</div>
        </div>

        {{-- Extras --}}
        <div class="one-card">
            <div class="one-card-title">Extras</div>

            <div class="one-hint" style="margin-top:6px;">
                Optional navbar features that aren’t normal links.
            </div>

            {{-- Theme toggle --}}
            <div style="margin-top:12px; display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <div>
                    <div class="one-label" style="font-size:13px;">Theme toggle button</div>
                    <div class="one-subline">Show Dark/Light switch in the navbar</div>
                </div>

                <label class="one-check" style="font-weight:800;">
                    <input type="checkbox" wire:model.live="themeToggleEnabled" />
                    @if (($themeToggleEnabled ?? false) === true)
                        <span class="one-badge one-badge-green">Enabled</span>
                    @else
                        <span class="one-badge one-badge-gray">Disabled</span>
                    @endif
                </label>
            </div>

            <div class="one-hint" style="margin-top:10px;">
                Note: this only controls whether the button shows — the front-end navbar view must render it.
            </div>

            <hr style="margin:14px 0; opacity:0.15;">

            {{-- NEW: Cart / Checkout quick add --}}
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                <div>
                    <div class="one-label" style="font-size:13px;">Cart / Checkout link</div>
                    <div class="one-subline">Ensure users can always access their basket and checkout</div>
                </div>

                <div style="display:flex; align-items:center; gap:8px;">
                    <button
                        type="button"
                        class="one-btn one-btn-primary"
                        wire:click="addCartLink"
                    >
                        Add cart link
                    </button>
                </div>
            </div>

            <div class="one-hint" style="margin-top:10px;">
                This adds an internal <code>/cart</code> link if one does not already exist.
            </div>
        </div>

        <div class="one-table">
            <div class="one-thead">
                <div>LABEL</div>
                <div>TYPE</div>
                <div>URL</div>
                <div>ORDER</div>
                <div>VISIBLE</div>
                <div>ENABLED</div>
                <div class="one-actions-col">ACTIONS</div>
            </div>

            @php
                $rows = $this->filteredLinks();

                $visLabel = function ($v) {
                    $v = (string) $v;
                    if ($v === 'guest') return 'Show: guest';
                    if ($v === 'auth') return 'Show: auth';
                    return 'Show: always';
                };

                $typeLabel = function ($type, $children) {
                    $t = (string) $type;
                    if ($t === 'dropdown' || (is_array($children) && count($children) > 0)) return 'Dropdown';
                    if ($t === 'external') return 'External';
                    return 'Internal';
                };
            @endphp

            @if (count($rows) === 0)
                <div class="one-empty">No links found.</div>
            @else
                @foreach ($rows as $row)
                    @php
                        $i = $row['_i'] ?? null;

                        $label = (string) ($row['label'] ?? '');
                        $url = (string) ($row['url'] ?? '');
                        $type = (string) ($row['type'] ?? 'internal');
                        $visibility = (string) ($row['visibility'] ?? 'always');
                        $enabled = (bool) ($row['enabled'] ?? true);
                        $visible = (bool) ($row['visible'] ?? true);

                        $children = $row['children'] ?? [];
                        $typeName = $typeLabel($type, $children);
                    @endphp

                    <div class="one-row">
                        <div>
                            <div class="one-label">{{ $label }}</div>
                            <div class="one-subline">{{ $visLabel($visibility) }}</div>

                            @if (is_array($children) && count($children) > 0)
                                <div class="one-subline">Dropdown: {{ count($children) }} child item(s)</div>
                            @endif
                        </div>

                        <div>
                            @if ($typeName === 'Dropdown')
                                <div class="one-badge one-badge-purple">Dropdown</div>
                            @elseif ($typeName === 'External')
                                <div class="one-badge one-badge-purple">External</div>
                            @else
                                <div class="one-badge one-badge-blue">Internal</div>
                            @endif
                        </div>

                        <div class="one-mono">{{ $url }}</div>

                        <div>
                            <div class="one-order">
                                <div class="one-order-num">
                                    @if ($i !== null)
                                        {{ ((int) $i) + 1 }}
                                    @else
                                        -
                                    @endif
                                </div>
                                <div class="one-order-btns">
                                    <button type="button" class="one-mini" @if ($i === null) disabled @endif wire:click="moveUp({{ (int) $i }})">Up</button>
                                    <button type="button" class="one-mini" @if ($i === null) disabled @endif wire:click="moveDown({{ (int) $i }})">Down</button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="one-check" style="font-weight:800;">
                                <input type="checkbox" @if ($i === null) disabled @endif wire:model.live="links.{{ (int) $i }}.visible" />
                                @if ($visible)
                                    <span class="one-badge one-badge-green">Visible</span>
                                @else
                                    <span class="one-badge one-badge-gray">Hidden</span>
                                @endif
                            </label>
                        </div>

                        <div>
                            <label class="one-check" style="font-weight:800;">
                                <input type="checkbox" @if ($i === null) disabled @endif wire:model.live="links.{{ (int) $i }}.enabled" />
                                @if ($enabled)
                                    <span class="one-badge one-badge-green">Enabled</span>
                                @else
                                    <span class="one-badge one-badge-gray">Disabled</span>
                                @endif
                            </label>
                        </div>

                        <div class="one-actions-col">
                            <button type="button" class="one-link one-link-edit" @if ($i === null) disabled @endif wire:click="openEdit({{ (int) $i }})">Edit</button>
                            <button type="button" class="one-link one-link-sub" @if ($i === null) disabled @endif wire:click="openChildManager({{ (int) $i }})">Children</button>
                            <button type="button" class="one-link one-link-del" @if ($i === null) disabled @endif wire:click="deleteLink({{ (int) $i }})">Delete</button>
                        </div>
                    </div>

                    {{-- Inline children rows --}}
                    @if (is_array($children) && count($children) > 0)
                        @foreach ($children as $child)
                            @php
                                $ci = $child['_ci'] ?? null;
                                $clabel = (string) ($child['label'] ?? '');
                                $curl = (string) ($child['url'] ?? '');
                                $cvis = (string) ($child['visibility'] ?? 'always');
                            @endphp

                            <div class="one-row-child">
                                <div>
                                    <div class="one-label">Child: {{ $clabel }}</div>
                                    <div class="one-subline">{{ $visLabel($cvis) }}</div>
                                </div>

                                <div>
                                    <div class="one-badge one-badge-gray">Child</div>
                                </div>

                                <div class="one-mono">{{ $curl }}</div>

                                <div><div class="one-muted">-</div></div>
                                <div><div class="one-muted">-</div></div>
                                <div><div class="one-muted">-</div></div>

                                <div class="one-actions-col">
                                    <button
                                        type="button"
                                        class="one-link one-link-edit"
                                        @if ($i === null || $ci === null) disabled @endif
                                        wire:click="openEditChild({{ (int) $i }}, {{ (int) $ci }})"
                                    >
                                        Edit child
                                    </button>

                                    {{-- delete child --}}
                                    <button
                                        type="button"
                                        class="one-link one-link-del"
                                        @if ($i === null || $ci === null) disabled @endif
                                        wire:click="deleteChild({{ (int) $i }}, {{ (int) $ci }})"
                                    >
                                        Delete child
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                @endforeach
            @endif
        </div>
    </div>

    {{-- Modal --}}
    @if (($modalOpen ?? false) === true)
        <div class="one-modal-wrap">
            <div class="one-modal-bg" wire:click="closeModal"></div>

            <div class="one-modal">
                <div class="one-modal-head">
                    <div>
                        <div class="one-modal-title">
                            @if (($modalIsChild ?? false) === true)
                                @if (($editingChildIndex ?? null) === null)
                                    New child
                                @else
                                    Edit child
                                @endif
                            @else
                                @if (($editingIndex ?? null) === null)
                                    New link
                                @else
                                    Edit link
                                @endif
                            @endif
                        </div>
                        <div class="one-modal-sub">Update label, URL, visibility, enabled and visible state.</div>
                    </div>

                    <button type="button" class="one-btn" wire:click="closeModal">Close</button>
                </div>

                @if (($modalIsChild ?? false) === true)
                    {{-- Optional: show quick child controls when managing children --}}
                    @php
                        $p = $editingParentIndex;
                        $kids = [];
                        if ($p !== null && isset($links[$p]['children']) && is_array($links[$p]['children'])) {
                            $kids = $links[$p]['children'];
                        }
                    @endphp

                    @if ($editingParentIndex !== null)
                        <div class="one-card" style="margin-top:14px;">
                            <div class="one-card-title-row">
                                <div class="one-card-title">Dropdown children</div>
                                <div class="one-actions">
                                    <button type="button" class="one-btn one-btn-primary" wire:click="openEditChild({{ (int) $editingParentIndex }}, -1)">New child</button>
                                </div>
                            </div>

                            <div style="margin-top:12px;">
                                @if (count($kids) === 0)
                                    <div class="one-muted">No children yet.</div>
                                @else
                                    @foreach ($kids as $kci => $k)
                                        @php
                                            $kLabel = (string) ($k['label'] ?? '');
                                            $kUrl = (string) ($k['url'] ?? '');
                                        @endphp

                                        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 0; border-bottom:1px solid rgba(255,255,255,0.08);">
                                            <div>
                                                <div class="one-label" style="font-size:13px;">{{ $kLabel }}</div>
                                                <div class="one-subline">{{ $kUrl }}</div>
                                            </div>

                                            <div style="display:flex; align-items:center; gap:8px;">
                                                <button type="button" class="one-mini" wire:click="moveChildUp({{ (int) $editingParentIndex }}, {{ (int) $kci }})">Up</button>
                                                <button type="button" class="one-mini" wire:click="moveChildDown({{ (int) $editingParentIndex }}, {{ (int) $kci }})">Down</button>
                                                <button type="button" class="one-btn" wire:click="openEditChild({{ (int) $editingParentIndex }}, {{ (int) $kci }})">Edit</button>
                                                <button type="button" class="one-btn" wire:click="deleteChild({{ (int) $editingParentIndex }}, {{ (int) $kci }})">Delete</button>
                                            </div>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="one-field">
                        <label class="one-label-sm">LABEL</label>
                        <input type="text" class="one-input" wire:model.defer="childLabel" placeholder="Game Servers" />
                        @error('childLabel') <div class="one-hint" style="color:#fca5a5;">{{ $message }}</div> @enderror
                    </div>

                    <div class="one-row2">
                        <div>
                            <label class="one-label-sm">TYPE</label>
                            <select class="one-select" wire:model.defer="childType">
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                            </select>
                        </div>

                        <div>
                            <label class="one-label-sm">VISIBILITY</label>
                            <select class="one-select" wire:model.defer="childVisibility">
                                <option value="always">Always</option>
                                <option value="guest">Guest</option>
                                <option value="auth">Auth</option>
                            </select>
                        </div>
                    </div>

                    <div class="one-field">
                        <label class="one-label-sm">URL</label>
                        <input type="text" class="one-input" wire:model.defer="childUrl" placeholder="/game-servers or https://docs.example.com" />
                        @error('childUrl') <div class="one-hint" style="color:#fca5a5;">{{ $message }}</div> @enderror
                    </div>

                    <div class="one-row2">
                        <div style="display:flex; align-items:flex-end; gap:18px; flex-wrap:wrap;">
                            <label class="one-check">
                                <input type="checkbox" wire:model.defer="childVisible" />
                                Visible
                            </label>

                            <label class="one-check">
                                <input type="checkbox" wire:model.defer="childEnabled" />
                                Enabled
                            </label>
                        </div>

                        <div></div>
                    </div>
                @else
                    <div class="one-field">
                        <label class="one-label-sm">LABEL</label>
                        <input type="text" class="one-input" wire:model.defer="formLabel" placeholder="Pricing" />
                        @error('formLabel') <div class="one-hint" style="color:#fca5a5;">{{ $message }}</div> @enderror
                    </div>

                    <div class="one-row2">
                        <div>
                            <label class="one-label-sm">TYPE</label>
                            <select class="one-select" wire:model.defer="formType">
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                                <option value="dropdown">Dropdown</option>
                            </select>
                        </div>

                        <div>
                            <label class="one-label-sm">VISIBILITY</label>
                            <select class="one-select" wire:model.defer="formVisibility">
                                <option value="always">Always</option>
                                <option value="guest">Guest</option>
                                <option value="auth">Auth</option>
                            </select>
                        </div>
                    </div>

                    <div class="one-field">
                        <label class="one-label-sm">URL</label>
                        <input type="text" class="one-input" wire:model.defer="formUrl" placeholder="/pricing or https://docs.example.com" />
                        @error('formUrl') <div class="one-hint" style="color:#fca5a5;">{{ $message }}</div> @enderror
                        <div class="one-hint">If Type is Dropdown, URL will be cleared automatically on save.</div>
                    </div>

                    <div class="one-row2">
                        <div style="display:flex; align-items:flex-end; gap:18px; flex-wrap:wrap;">
                            <label class="one-check">
                                <input type="checkbox" wire:model.defer="formVisible" />
                                Visible
                            </label>

                            <label class="one-check">
                                <input type="checkbox" wire:model.defer="formEnabled" />
                                Enabled
                            </label>
                        </div>

                        <div></div>
                    </div>
                @endif

                <div class="one-modal-actions">
                    <button type="button" class="one-btn" wire:click="closeModal">Cancel</button>
                    <button type="button" class="one-btn one-btn-primary" wire:click="saveModal">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
