<div>
    <div class="one-header">
        <div>
            <h1 class="one-title">Footer</h1>
            <div class="one-sub">Edit columns, links, visibility, and ordering. Saves to setting key: obsidian.footer</div>
        </div>

        <div class="one-actions">
            <button type="button" class="one-btn one-btn-primary" wire:click="openNewColumn">Add column</button>
            <button type="button" class="one-btn" wire:click="saveAll">Save</button>
        </div>
    </div>

    @php
        $statusText = '';
        $sess = session('status');
        if (is_string($sess) && $sess !== '') {
            $statusText = $sess;
        }

        $visLabel = function ($v) {
            $v = (string) $v;
            if ($v === 'guest') return 'Show: guest';
            if ($v === 'auth') return 'Show: auth';
            return 'Show: always';
        };

        $typeLabel = function ($t) {
            $t = (string) $t;
            if ($t === 'external') return 'External';
            return 'Internal';
        };
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
                @php $preview = $this->previewFooter(); @endphp

                @if (!(bool) ($preview['enabled'] ?? true))
                    <div class="one-muted">Footer disabled.</div>
                @else
                    @php $pcols = $preview['columns'] ?? []; @endphp

                    @if (!is_array($pcols) || count($pcols) === 0)
                        <div class="one-muted">Nothing enabled.</div>
                    @else
                        <div class="one-pill-row">
                            @foreach ($pcols as $pc)
                                <div class="one-pill">{{ (string) ($pc['title'] ?? '') }}</div>
                            @endforeach
                        </div>

                        <div class="one-hint" style="margin-top:12px;">Preview shows enabled + visible columns and links for the selected mode.</div>
                    @endif
                @endif
            </div>
        </div>

        <div class="one-card">
            <div class="one-card-title">Settings</div>

            <div class="one-row2">
                <div class="one-field" style="margin-top:12px;">
                    <label class="one-check" style="font-weight:800;">
                        <input type="checkbox" wire:model.live="footerEnabled" />
                        @if ((bool) $footerEnabled)
                            <span class="one-badge one-badge-green">Enabled</span>
                        @else
                            <span class="one-badge one-badge-gray">Disabled</span>
                        @endif
                    </label>
                    <div class="one-hint">Disable to hide the footer globally on all pages.</div>
                </div>

                <div></div>
            </div>

            <div class="one-field">
                <label class="one-label-sm">COPYRIGHT TEXT (OPTIONAL)</label>
                <input type="text" class="one-input" wire:model.defer="copyrightText" placeholder="(c) {{ date('Y') }} TamelessHosting. All rights reserved." />
                <div class="one-hint">Shown in the footer if set. Leave blank to hide.</div>
            </div>
        </div>

        <div class="one-table">
            <div class="one-thead">
                <div>COLUMN / LINK</div>
                <div>TYPE</div>
                <div>URL</div>
                <div>ORDER</div>
                <div>VISIBLE</div>
                <div>ENABLED</div>
                <div class="one-actions-col">ACTIONS</div>
            </div>

            @php
                $cols = is_array($columns ?? null) ? $columns : [];
            @endphp

            @if (count($cols) === 0)
                <div class="one-empty">No columns yet. Click Add column to start.</div>
            @else
                @foreach ($cols as $ci => $col)
                    @php
                        $colTitle = (string) ($col['title'] ?? '');
                        $colVisibility = (string) ($col['visibility'] ?? 'always');
                        $colEnabled = (bool) ($col['enabled'] ?? true);
                        $colVisible = (bool) ($col['visible'] ?? true);
                        $links = $col['links'] ?? [];
                        $links = is_array($links) ? $links : [];
                    @endphp

                    <div class="one-row">
                        <div>
                            <div class="one-label">{{ $colTitle }}</div>
                            <div class="one-subline">{{ $visLabel($colVisibility) }}</div>
                            <div class="one-subline">Links: {{ count($links) }}</div>
                        </div>

                        <div>
                            <div class="one-badge one-badge-purple">Column</div>
                        </div>

                        <div class="one-mono">-</div>

                        <div>
                            <div class="one-order">
                                <div class="one-order-num">{{ ((int) $ci) + 1 }}</div>
                                <div class="one-order-btns">
                                    <button type="button" class="one-mini" wire:click="moveColumnUp({{ (int) $ci }})">Up</button>
                                    <button type="button" class="one-mini" wire:click="moveColumnDown({{ (int) $ci }})">Down</button>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="one-check" style="font-weight:800;">
                                <input type="checkbox" wire:model.live="columns.{{ (int) $ci }}.visible" />
                                @if ($colVisible)
                                    <span class="one-badge one-badge-green">Visible</span>
                                @else
                                    <span class="one-badge one-badge-gray">Hidden</span>
                                @endif
                            </label>
                        </div>

                        <div>
                            <label class="one-check" style="font-weight:800;">
                                <input type="checkbox" wire:model.live="columns.{{ (int) $ci }}.enabled" />
                                @if ($colEnabled)
                                    <span class="one-badge one-badge-green">Enabled</span>
                                @else
                                    <span class="one-badge one-badge-gray">Disabled</span>
                                @endif
                            </label>
                        </div>

                        <div class="one-actions-col">
                            <button type="button" class="one-link one-link-edit" wire:click="openEditColumn({{ (int) $ci }})">Edit</button>
                            <button type="button" class="one-link one-link-sub" wire:click="openNewLink({{ (int) $ci }})">Add link</button>
                            <button type="button" class="one-link one-link-del" wire:click="deleteColumn({{ (int) $ci }})">Delete</button>
                        </div>
                    </div>

                    {{-- Inline link rows (children) --}}
                    @if (count($links) > 0)
                        @foreach ($links as $li => $link)
                            @php
                                $lLabel = (string) ($link['label'] ?? '');
                                $lUrl = (string) ($link['url'] ?? '');
                                $lType = (string) ($link['type'] ?? 'internal');
                                $lVisibility = (string) ($link['visibility'] ?? 'always');
                                $lEnabled = (bool) ($link['enabled'] ?? true);
                                $lVisible = (bool) ($link['visible'] ?? true);
                            @endphp

                            <div class="one-row-child">
                                <div>
                                    <div class="one-label">Link: {{ $lLabel }}</div>
                                    <div class="one-subline">{{ $visLabel($lVisibility) }}</div>
                                </div>

                                <div>
                                    @if ($typeLabel($lType) === 'External')
                                        <div class="one-badge one-badge-purple">External</div>
                                    @else
                                        <div class="one-badge one-badge-blue">Internal</div>
                                    @endif
                                </div>

                                <div class="one-mono">{{ $lUrl }}</div>

                                <div>
                                    <div class="one-order">
                                        <div class="one-order-num">{{ ((int) $li) + 1 }}</div>
                                        <div class="one-order-btns">
                                            <button type="button" class="one-mini" wire:click="moveLinkUp({{ (int) $ci }}, {{ (int) $li }})">Up</button>
                                            <button type="button" class="one-mini" wire:click="moveLinkDown({{ (int) $ci }}, {{ (int) $li }})">Down</button>
                                        </div>
                                    </div>
                                </div>

                                <div>
                                    <label class="one-check" style="font-weight:800;">
                                        <input type="checkbox" wire:model.live="columns.{{ (int) $ci }}.links.{{ (int) $li }}.visible" />
                                        @if ($lVisible)
                                            <span class="one-badge one-badge-green">Visible</span>
                                        @else
                                            <span class="one-badge one-badge-gray">Hidden</span>
                                        @endif
                                    </label>
                                </div>

                                <div>
                                    <label class="one-check" style="font-weight:800;">
                                        <input type="checkbox" wire:model.live="columns.{{ (int) $ci }}.links.{{ (int) $li }}.enabled" />
                                        @if ($lEnabled)
                                            <span class="one-badge one-badge-green">Enabled</span>
                                        @else
                                            <span class="one-badge one-badge-gray">Disabled</span>
                                        @endif
                                    </label>
                                </div>

                                <div class="one-actions-col">
                                    <button type="button" class="one-link one-link-edit" wire:click="openEditLink({{ (int) $ci }}, {{ (int) $li }})">Edit</button>
                                    <button type="button" class="one-link one-link-del" wire:click="deleteLink({{ (int) $ci }}, {{ (int) $li }})">Delete</button>
                                </div>
                            </div>
                        @endforeach
                    @endif
                @endforeach
            @endif
        </div>
    </div>

    {{-- Column modal --}}
    @if (($showColumnModal ?? false) === true)
        <div class="one-modal-wrap">
            <div class="one-modal-bg" wire:click="closeModal"></div>

            <div class="one-modal">
                <div class="one-modal-head">
                    <div>
                        <div class="one-modal-title">
                            @if (($editingColumnIndex ?? -1) < 0)
                                New column
                            @else
                                Edit column
                            @endif
                        </div>
                        <div class="one-modal-sub">Update title, visibility, enabled and visible state.</div>
                    </div>

                    <button type="button" class="one-btn" wire:click="closeModal">Close</button>
                </div>

                <div class="one-field">
                    <label class="one-label-sm">TITLE</label>
                    <input type="text" class="one-input" wire:model.defer="colTitle" placeholder="Company" />
                </div>

                <div class="one-row2">
                    <div>
                        <label class="one-label-sm">VISIBILITY</label>
                        <select class="one-select" wire:model.defer="colVisibility">
                            <option value="always">Always</option>
                            <option value="guest">Guest</option>
                            <option value="auth">Auth</option>
                        </select>
                    </div>

                    <div></div>
                </div>

                <div class="one-row2">
                    <div style="display:flex; align-items:flex-end; gap:18px; flex-wrap:wrap;">
                        <label class="one-check">
                            <input type="checkbox" wire:model.defer="colVisible" />
                            Visible
                        </label>

                        <label class="one-check">
                            <input type="checkbox" wire:model.defer="colEnabled" />
                            Enabled
                        </label>
                    </div>

                    <div></div>
                </div>

                <div class="one-modal-actions">
                    <button type="button" class="one-btn" wire:click="closeModal">Cancel</button>
                    <button type="button" class="one-btn one-btn-primary" wire:click="saveColumnModal">Save</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Link modal --}}
    @if (($showLinkModal ?? false) === true)
        <div class="one-modal-wrap">
            <div class="one-modal-bg" wire:click="closeModal"></div>

            <div class="one-modal">
                <div class="one-modal-head">
                    <div>
                        <div class="one-modal-title">
                            @if (($editingLinkIndex ?? -1) < 0)
                                New link
                            @else
                                Edit link
                            @endif
                        </div>
                        <div class="one-modal-sub">Update label, URL, type, visibility, enabled and visible state.</div>
                    </div>

                    <button type="button" class="one-btn" wire:click="closeModal">Close</button>
                </div>

                <div class="one-field">
                    <label class="one-label-sm">LABEL</label>
                    <input type="text" class="one-input" wire:model.defer="linkLabel" placeholder="Status" />
                </div>

                <div class="one-row2">
                    <div>
                        <label class="one-label-sm">TYPE</label>
                        <select class="one-select" wire:model.defer="linkType">
                            <option value="internal">Internal</option>
                            <option value="external">External</option>
                        </select>
                    </div>

                    <div>
                        <label class="one-label-sm">VISIBILITY</label>
                        <select class="one-select" wire:model.defer="linkVisibility">
                            <option value="always">Always</option>
                            <option value="guest">Guest</option>
                            <option value="auth">Auth</option>
                        </select>
                    </div>
                </div>

                <div class="one-field">
                    <label class="one-label-sm">URL</label>
                    <input type="text" class="one-input" wire:model.defer="linkUrl" placeholder="/status or https://example.com" />
                    <div class="one-hint">Internal links can be relative paths. External links should include https://</div>
                </div>

                <div class="one-row2">
                    <div style="display:flex; align-items:flex-end; gap:18px; flex-wrap:wrap;">
                        <label class="one-check">
                            <input type="checkbox" wire:model.defer="linkVisible" />
                            Visible
                        </label>

                        <label class="one-check">
                            <input type="checkbox" wire:model.defer="linkEnabled" />
                            Enabled
                        </label>
                    </div>

                    <div></div>
                </div>

                <div class="one-modal-actions">
                    <button type="button" class="one-btn" wire:click="closeModal">Cancel</button>
                    <button type="button" class="one-btn one-btn-primary" wire:click="saveLinkModal">Save</button>
                </div>
            </div>
        </div>
    @endif
</div>
