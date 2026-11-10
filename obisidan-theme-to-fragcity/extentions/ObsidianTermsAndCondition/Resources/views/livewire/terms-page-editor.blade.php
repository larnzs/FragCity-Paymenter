<div>
    {{-- Header --}}
    <div class="one-header">
        <div>
            <h1 class="one-title">Terms</h1>
            <div class="one-sub">
                Terms &amp; Conditions Page<br>
                <span style="color: rgba(255,255,255,0.45);">Everything on the public Terms page is editable here.</span>
            </div>
        </div>

        <div class="one-actions">
            <button type="button" wire:click="resetToDefaults" class="one-btn one-btn-danger">
                Reset to defaults
            </button>

            <button type="button" wire:click="save" class="one-btn one-btn-primary">
                Save
            </button>
        </div>
    </div>

    {{-- Status --}}
    @if (!empty($status))
        <div class="one-status">
            {{ $status }}
        </div>
    @endif

    <div class="one-grid">
        {{-- SEO --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <div>
                    <div class="one-card-title">SEO</div>
                    <div class="one-card-sub">Page title and metadata.</div>
                </div>
            </div>

            <div class="one-row2">
                <div class="one-field">
                    <label class="one-label-sm">SEO title</label>
                    <input type="text" wire:model.lazy="cfg.seo.title" class="one-input" placeholder="Terms &amp; Conditions">
                </div>

                <div class="one-field">
                    <label class="one-label-sm">Robots</label>
                    <input type="text" wire:model.lazy="cfg.seo.robots" class="one-input" placeholder="index,follow">
                </div>
            </div>

            <div class="one-row2">
                <div class="one-field">
                    <label class="one-label-sm">SEO description</label>
                    <textarea rows="3" wire:model.lazy="cfg.seo.description" class="one-textarea" placeholder="Short description for search engines..."></textarea>
                </div>

                <div class="one-field">
                    <label class="one-label-sm">Canonical URL</label>
                    <input type="text" wire:model.lazy="cfg.seo.canonical_url" class="one-input" placeholder="https://example.com/terms">
                </div>
            </div>
        </div>

        {{-- HERO --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <div>
                    <div class="one-card-title">Hero</div>
                    <div class="one-card-sub">Top card with title, summary, and actions.</div>
                </div>

                <label class="one-check" style="margin:0;">
                    <input type="checkbox" wire:model="cfg.hero.enabled">
                    Enabled
                </label>
            </div>

            <div class="one-row2">
                <div class="one-field">
                    <label class="one-label-sm">Page title</label>
                    <input type="text" wire:model.lazy="cfg.hero.title" class="one-input" placeholder="Terms &amp; Conditions">
                </div>

                <div class="one-field">
                    <label class="one-label-sm">Summary</label>
                    <textarea rows="3" wire:model.lazy="cfg.hero.summary" class="one-textarea" placeholder="Short intro shown at the top..."></textarea>
                </div>
            </div>

            <div class="one-divider"></div>

            {{-- Last updated badge --}}
            <div class="one-section-box">
                <div class="one-section-head">
                    <div>
                        <div class="one-card-title" style="font-size:14px;">Last updated badge</div>
                        <div class="one-hint">Shown above the title.</div>
                    </div>

                    <label class="one-check" style="margin:0;">
                        <input type="checkbox" wire:model="cfg.hero.last_updated.enabled">
                        Enabled
                    </label>
                </div>

                <div class="one-row3">
                    <div class="one-field">
                        <label class="one-label-sm">Label</label>
                        <input type="text" wire:model.lazy="cfg.hero.last_updated.label" class="one-input" placeholder="Last updated:">
                    </div>

                    <div class="one-field">
                        <label class="one-label-sm">Date</label>
                        <input type="text" wire:model.lazy="cfg.hero.last_updated.date" class="one-input" placeholder="2025-12-27">
                    </div>

                    <div class="one-field">
                        <label class="one-label-sm">Display override</label>
                        <input type="text" wire:model.lazy="cfg.hero.last_updated.display" class="one-input" placeholder="December 27, 2025">
                    </div>
                </div>
            </div>

            <div class="one-divider"></div>

            {{-- Hero buttons --}}
            <div class="one-section-box">
                <div class="one-section-head">
                    <div>
                        <div class="one-card-title" style="font-size:14px;">Hero buttons</div>
                        <div class="one-hint">Buttons shown on the hero card.</div>
                    </div>

                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <label class="one-check" style="margin:0;">
                            <input type="checkbox" wire:model="cfg.hero.actions.enabled">
                            Enabled
                        </label>

                        <button type="button" wire:click="addHeroButton" class="one-mini">
                            Add button
                        </button>
                    </div>
                </div>

                @php($heroButtons = data_get($cfg, 'hero.actions.buttons', []))

                @if (empty($heroButtons))
                    <div class="one-hint" style="margin-top:12px;">No hero buttons yet. Click “Add button” to create one.</div>
                @endif

                @foreach ($heroButtons as $i => $btn)
                    <div class="one-section-box" style="margin-top:12px;">
                        <div class="one-section-head">
                            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                <span class="one-pill">Button #{{ $i + 1 }}</span>
                            </div>

                            <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                <button type="button" wire:click="moveHeroButtonUp({{ $i }})" class="one-mini">Up</button>
                                <button type="button" wire:click="moveHeroButtonDown({{ $i }})" class="one-mini">Down</button>
                                <button type="button" wire:click="removeHeroButton({{ $i }})" class="one-mini" style="border-color: rgba(248,113,113,0.35); color: rgba(254,202,202,0.95);">
                                    Remove
                                </button>
                            </div>
                        </div>

                        <div class="one-row2">
                            <label class="one-check" style="margin-top:14px;">
                                <input type="checkbox" wire:model="cfg.hero.actions.buttons.{{ $i }}.enabled">
                                Enabled
                            </label>

                            <label class="one-check" style="margin-top:14px;">
                                <input type="checkbox" wire:model="cfg.hero.actions.buttons.{{ $i }}.new_tab">
                                Open in new tab
                            </label>
                        </div>

                        <div class="one-row2">
                            <div class="one-field">
                                <label class="one-label-sm">Label</label>
                                <input type="text" wire:model.lazy="cfg.hero.actions.buttons.{{ $i }}.label" class="one-input" placeholder="Read more">
                            </div>

                            <div class="one-field">
                                <label class="one-label-sm">URL</label>
                                <input type="text" wire:model.lazy="cfg.hero.actions.buttons.{{ $i }}.url" class="one-input" placeholder="/contact">
                            </div>
                        </div>

                        <div class="one-field">
                            <label class="one-label-sm">Style</label>
                            <select wire:model="cfg.hero.actions.buttons.{{ $i }}.style" class="one-select">
                                <option value="primary">Primary</option>
                                <option value="secondary">Secondary</option>
                                <option value="ghost">Ghost</option>
                            </select>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- TOC --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <div>
                    <div class="one-card-title">Table of Contents</div>
                    <div class="one-card-sub">Right sidebar list of sections.</div>
                </div>

                <label class="one-check" style="margin:0;">
                    <input type="checkbox" wire:model="cfg.toc.enabled">
                    Enabled
                </label>
            </div>

            <div class="one-row2">
                <div class="one-field">
                    <label class="one-label-sm">Title</label>
                    <input type="text" wire:model.lazy="cfg.toc.title" class="one-input" placeholder="Table of contents">
                </div>

                <label class="one-check" style="margin-top:34px;">
                    <input type="checkbox" wire:model="cfg.toc.mobile_collapse.enabled">
                    Mobile collapsible
                </label>
            </div>
        </div>

        {{-- Sections --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <div>
                    <div class="one-card-title">Sections</div>
                    <div class="one-card-sub">Add, remove, order, and edit every clause.</div>
                </div>

                <button type="button" wire:click="addSection" class="one-btn">
                    Add section
                </button>
            </div>

            @php($sections = data_get($cfg, 'sections', []))

            @if (empty($sections))
                <div class="one-hint" style="margin-top:12px;">No sections yet. Click “Add section” to create one.</div>
            @endif

            @foreach ($sections as $si => $section)
                <div class="one-section-box">
                    <div class="one-section-head">
                        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <span class="one-pill">Section #{{ $si + 1 }}</span>
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" wire:click="moveSectionUp({{ $si }})" class="one-mini">Up</button>
                            <button type="button" wire:click="moveSectionDown({{ $si }})" class="one-mini">Down</button>
                            <button type="button" wire:click="removeSection({{ $si }})" class="one-mini" style="border-color: rgba(248,113,113,0.35); color: rgba(254,202,202,0.95);">
                                Remove
                            </button>
                        </div>
                    </div>

                    <div class="one-row2">
                        <label class="one-check" style="margin-top:14px;">
                            <input type="checkbox" wire:model="cfg.sections.{{ $si }}.enabled">
                            Enabled
                        </label>

                        <label class="one-check" style="margin-top:14px;">
                            <input type="checkbox" wire:model="cfg.sections.{{ $si }}.visible_in_toc">
                            Show in TOC
                        </label>
                    </div>

                    <div class="one-row2">
                        <div class="one-field">
                            <label class="one-label-sm">Number</label>
                            <input type="text" wire:model.lazy="cfg.sections.{{ $si }}.number" class="one-input" placeholder="1">
                        </div>

                        <div class="one-field">
                            <label class="one-label-sm">Anchor ID</label>
                            <input type="text" wire:model.lazy="cfg.sections.{{ $si }}.id" class="one-input" placeholder="introduction">
                        </div>
                    </div>

                    <div class="one-field">
                        <label class="one-label-sm">Title</label>
                        <input type="text" wire:model.lazy="cfg.sections.{{ $si }}.title" class="one-input" placeholder="Introduction">
                    </div>

                    <div class="one-divider"></div>

                    {{-- Blocks --}}
                    <div class="one-section-head">
                        <div>
                            <div class="one-card-title" style="font-size:14px;">Blocks</div>
                            <div class="one-hint">Paragraphs, headings, bullets, and callouts.</div>
                        </div>

                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" wire:click="addBlock({{ $si }}, 'paragraph')" class="one-mini">+ Paragraph</button>
                            <button type="button" wire:click="addBlock({{ $si }}, 'heading')" class="one-mini">+ Heading</button>
                            <button type="button" wire:click="addBlock({{ $si }}, 'bullets')" class="one-mini">+ Bullets</button>
                            <button type="button" wire:click="addBlock({{ $si }}, 'callout')" class="one-mini">+ Callout</button>
                        </div>
                    </div>

                    @php($blocks = data_get($section, 'blocks', []))

                    @if (empty($blocks))
                        <div class="one-hint" style="margin-top:12px;">No blocks in this section yet.</div>
                    @endif

                    @foreach ($blocks as $bi => $block)
                        @php($type = (string) data_get($block, 'type'))

                        <div class="one-section-box" style="margin-top:12px;">
                            <div class="one-section-head">
                                <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                                    <span class="one-pill">Block #{{ $bi + 1 }}</span>
                                    <span class="one-pill" style="border-color: rgba(168,85,247,0.35); background: rgba(168,85,247,0.12); color: rgba(216,180,254,0.95);">
                                        {{ strtoupper($type) }}
                                    </span>
                                </div>

                                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                                    <button type="button" wire:click="moveBlockUp({{ $si }}, {{ $bi }})" class="one-mini">Up</button>
                                    <button type="button" wire:click="moveBlockDown({{ $si }}, {{ $bi }})" class="one-mini">Down</button>
                                    <button type="button" wire:click="removeBlock({{ $si }}, {{ $bi }})" class="one-mini" style="border-color: rgba(248,113,113,0.35); color: rgba(254,202,202,0.95);">
                                        Remove
                                    </button>
                                </div>
                            </div>

                            <label class="one-check" style="margin-top:14px;">
                                <input type="checkbox" wire:model="cfg.sections.{{ $si }}.blocks.{{ $bi }}.enabled">
                                Enabled
                            </label>

                            @if ($type === 'callout')
                                <div class="one-field">
                                    <label class="one-label-sm">Variant</label>
                                    <select wire:model="cfg.sections.{{ $si }}.blocks.{{ $bi }}.variant" class="one-select">
                                        <option value="info">Info</option>
                                        <option value="warning">Warning</option>
                                        <option value="definition">Definition</option>
                                    </select>
                                </div>
                            @endif

                            @if (in_array($type, ['paragraph', 'heading', 'callout'], true))
                                @if ($type === 'callout')
                                    <div class="one-field">
                                        <label class="one-label-sm">Callout title</label>
                                        <input type="text" wire:model.lazy="cfg.sections.{{ $si }}.blocks.{{ $bi }}.title" class="one-input" placeholder="Important">
                                    </div>
                                @else
                                    <div class="one-field">
                                        <label class="one-label-sm">{{ $type === 'heading' ? 'Heading text' : 'Text' }}</label>
                                        <textarea rows="{{ $type === 'heading' ? 2 : 3 }}"
                                                  wire:model.lazy="cfg.sections.{{ $si }}.blocks.{{ $bi }}.text"
                                                  class="one-textarea"
                                                  placeholder="Write content here..."></textarea>
                                    </div>
                                @endif
                            @endif

                            @if ($type === 'callout')
                                <div class="one-field">
                                    <label class="one-label-sm">Callout text</label>
                                    <textarea rows="3" wire:model.lazy="cfg.sections.{{ $si }}.blocks.{{ $bi }}.text" class="one-textarea" placeholder="Callout body..."></textarea>
                                </div>
                            @endif

                            @if ($type === 'bullets')
                                <div class="one-divider"></div>

                                <div class="one-section-head">
                                    <div>
                                        <div class="one-card-title" style="font-size:14px;">Bullet items</div>
                                        <div class="one-hint">Add and edit bullet points.</div>
                                    </div>

                                    <button type="button" wire:click="addBulletItem({{ $si }}, {{ $bi }})" class="one-mini">
                                        Add item
                                    </button>
                                </div>

                                @php($items = data_get($block, 'items', []))

                                @foreach ($items as $ii => $item)
                                    <div class="one-row2">
                                        <div class="one-field">
                                            <label class="one-label-sm">Item #{{ $ii + 1 }}</label>
                                            <input type="text"
                                                   wire:model.lazy="cfg.sections.{{ $si }}.blocks.{{ $bi }}.items.{{ $ii }}.text"
                                                   class="one-input"
                                                   placeholder="Bullet text...">
                                        </div>

                                        <div class="one-field" style="display:flex; align-items:flex-end;">
                                            <button type="button"
                                                    wire:click="removeBulletItem({{ $si }}, {{ $bi }}, {{ $ii }})"
                                                    class="one-btn one-btn-danger"
                                                    style="width:100%; justify-content:center;">
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                @endforeach

                                @if (empty($items))
                                    <div class="one-hint" style="margin-top:12px;">No bullet items yet. Click “Add item”.</div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>

        {{-- Footer --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <div>
                    <div class="one-card-title">Footer</div>
                    <div class="one-card-sub">Bottom bar text and links.</div>
                </div>

                <label class="one-check" style="margin:0;">
                    <input type="checkbox" wire:model="cfg.footer.enabled">
                    Enabled
                </label>
            </div>

            <div class="one-field">
                <label class="one-label-sm">Left text</label>
                <input type="text" wire:model.lazy="cfg.footer.left_text" class="one-input" placeholder="© TamelessHosting. All rights reserved.">
            </div>

            <div class="one-divider"></div>

            <div class="one-section-head">
                <div>
                    <div class="one-card-title" style="font-size:14px;">Footer links</div>
                    <div class="one-hint">Right side links.</div>
                </div>

                <button type="button" wire:click="addFooterLink" class="one-mini">Add link</button>
            </div>

            @php($footerLinks = data_get($cfg, 'footer.links', []))

            @if (empty($footerLinks))
                <div class="one-hint" style="margin-top:12px;">No footer links yet. Click “Add link”.</div>
            @endif

            @foreach ($footerLinks as $i => $link)
                <div class="one-section-box" style="margin-top:12px;">
                    <div class="one-section-head">
                        <span class="one-pill">Link #{{ $i + 1 }}</span>

                        <div style="display:flex; gap:8px; flex-wrap:wrap;">
                            <button type="button" wire:click="moveFooterLinkUp({{ $i }})" class="one-mini">Up</button>
                            <button type="button" wire:click="moveFooterLinkDown({{ $i }})" class="one-mini">Down</button>
                            <button type="button" wire:click="removeFooterLink({{ $i }})" class="one-mini" style="border-color: rgba(248,113,113,0.35); color: rgba(254,202,202,0.95);">
                                Remove
                            </button>
                        </div>
                    </div>

                    <div class="one-row2">
                        <label class="one-check" style="margin-top:14px;">
                            <input type="checkbox" wire:model="cfg.footer.links.{{ $i }}.enabled">
                            Enabled
                        </label>

                        <label class="one-check" style="margin-top:14px;">
                            <input type="checkbox" wire:model="cfg.footer.links.{{ $i }}.new_tab">
                            Open in new tab
                        </label>
                    </div>

                    <div class="one-row2">
                        <div class="one-field">
                            <label class="one-label-sm">Label</label>
                            <input type="text" wire:model.lazy="cfg.footer.links.{{ $i }}.label" class="one-input" placeholder="Privacy policy">
                        </div>

                        <div class="one-field">
                            <label class="one-label-sm">URL</label>
                            <input type="text" wire:model.lazy="cfg.footer.links.{{ $i }}.url" class="one-input" placeholder="/privacy">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
