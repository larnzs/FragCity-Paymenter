{{-- extensions/Others/ObsidianAboutPage/Resources/views/livewire/about-page-editor.blade.php --}}

<div class="obs-about-editor space-y-7">
    @php
        // Obsidian vibe (professional, readable, no "white page" sections)
        $section = 'fi-section';

        // Main section card
        $box = 'rounded-2xl border border-white/10 bg-white/[0.04] backdrop-blur-xl shadow-[0_0_0_1px_rgba(255,255,255,0.02)] overflow-hidden';

        $contentCtn = 'fi-section-content-ctn';
        $content = 'fi-section-content';

        // extra spacing between columns + slight row gap to stop "clipping"
        $row2 = 'grid grid-cols-1 gap-x-6 gap-y-5 md:grid-cols-2';

        $field = 'fi-fo-field-wrp';
        $label = 'fi-fo-field-wrp-label';

        // Inputs
        $wrap  = 'fi-input-wrp';
        $input = 'fi-input';
        $select = 'fi-select-input';

        // Buttons
        $btn = 'fi-btn fi-btn-outlined fi-size-sm';
        $btnPrimary = 'fi-btn fi-btn-color-primary fi-size-sm';
        $btnDanger = 'fi-btn fi-btn-color-danger fi-btn-outlined fi-size-sm';

        // Inner cards (CTAs / Stat blocks etc.)
        $inner = 'rounded-2xl border border-white/10 bg-black/20 shadow-none hover:bg-white/[0.05] transition';

        // Toggle should look like a normal Filament button (same vibe as Remove)
        $toggleOn  = 'fi-btn fi-btn-outlined fi-btn-color-success fi-size-sm';
        $toggleOff = 'fi-btn fi-btn-outlined fi-size-sm';

        // Base images folder (public path)
        $imgBase = trim((string)($publicImagesBase ?? 'assets/images'), '/');

        // Helper to build a preview URL from a filename (NO folders allowed per your hint)
        $previewUrl = function (?string $file) use ($imgBase) {
            $file = trim((string) $file);
            if ($file === '') return '';
            // Force filename-only usage (strip any path parts)
            $file = basename($file);
            return url($imgBase . '/' . $file);
        };

        // Hero preview url
        $heroFile = (string) data_get($cfg, 'hero.image.file', '');
        $heroPreview = $previewUrl($heroFile);
    @endphp

    <style>
        .obs-about-editor,
        .obs-about-editor * {
            --tw-ring-shadow: 0 0 #0000 !important;
            --tw-ring-offset-shadow: 0 0 #0000 !important;
            --tw-ring-color: transparent !important;
            --tw-ring-offset-width: 0px !important;
        }

        /* Reduce Filament default shadows/outlines for Obsidian look */
        .obs-about-editor .fi-section,
        .obs-about-editor .fi-card,
        .obs-about-editor .fi-panel,
        .obs-about-editor .fi-wi,
        .obs-about-editor .fi-ta,
        .obs-about-editor .fi-fo-section,
        .obs-about-editor .fi-fo-field-wrp,
        .obs-about-editor .fi-input-wrp,
        .obs-about-editor .fi-input,
        .obs-about-editor .fi-select-input,
        .obs-about-editor .fi-fo-toggle,
        .obs-about-editor .fi-fo-checkbox,
        .obs-about-editor [class*="ring-"],
        .obs-about-editor [class*="outline-"] {
            box-shadow: none !important;
            outline: none !important;
        }

        /* Inputs: readable, consistent */
        .obs-about-editor .fi-input-wrp {
            background: rgba(255,255,255,0.04) !important;
            border: 1px solid rgba(255,255,255,0.10) !important;
            border-radius: 14px !important;
            padding: 2px !important;
        }

        .obs-about-editor .fi-input,
        .obs-about-editor .fi-select-input {
            background: transparent !important;
            border-radius: 12px !important;
            min-width: 0 !important;
            color: rgba(255,255,255,0.92) !important;
        }

        .obs-about-editor input.fi-input {
            width: 100% !important;
            display: block !important;
        }

        .obs-about-editor .fi-section-content,
        .obs-about-editor .fi-section-content-ctn,
        .obs-about-editor .fi-fo-field-wrp,
        .obs-about-editor .fi-input-wrp {
            min-width: 0 !important;
        }

        .obs-about-editor .fi-section-header {
            padding-bottom: 10px;
        }

        .obs-about-editor .obs-toggle {
            border-radius: 9999px !important;
        }

        .obs-about-editor .obs-note {
            color: rgba(255,255,255,0.65);
            font-size: 12px;
            margin-top: 6px;
        }

        .obs-about-editor .obs-hint {
            color: rgba(255,255,255,0.55);
            font-size: 12px;
            margin-top: 6px;
            line-height: 1.35;
        }

        .obs-about-editor .obs-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 9999px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.08);
            color: rgba(255,255,255,0.80);
            font-size: 12px;
        }
        .obs-about-editor .obs-pill code {
            font-size: 12px;
            color: rgba(255,255,255,0.92);
        }

        /* Slider */
        .obs-about-editor .obs-range {
            width: 100%;
            accent-color: #a855f7;
        }
        .obs-about-editor .obs-range-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .obs-about-editor .obs-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            border-radius: 9999px;
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(0,0,0,0.30);
            color: rgba(255,255,255,0.85);
            font-size: 12px;
            font-variant-numeric: tabular-nums;
            min-width: 64px;
        }

        /* Preview image box */
        .obs-about-editor .obs-preview {
            border: 1px solid rgba(255,255,255,0.10);
            background: rgba(0,0,0,0.25);
            border-radius: 16px;
            overflow: hidden;
        }
        .obs-about-editor .obs-preview img {
            width: 100%;
            height: auto;
            display: block;
        }
        .obs-about-editor .obs-preview-empty {
            padding: 14px;
            font-size: 12px;
            color: rgba(255,255,255,0.55);
        }
    </style>

    {{-- Header / Actions --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="text-xl font-semibold fi-header-heading">About Page Editor</div>
            <div class="text-sm fi-header-subheading">No-code settings for the About page.</div>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                wire:click="resetToDefaults"
                wire:loading.attr="disabled"
                wire:target="resetToDefaults"
                class="{{ $btn }}"
            >
                <span wire:loading.remove wire:target="resetToDefaults">Reset</span>
                <span wire:loading wire:target="resetToDefaults">Resetting...</span>
            </button>

            <button
                type="button"
                wire:click="save"
                wire:loading.attr="disabled"
                wire:target="save"
                class="{{ $btnPrimary }}"
            >
                <span wire:loading.remove wire:target="save">Save</span>
                <span wire:loading wire:target="save">Saving...</span>
            </button>
        </div>
    </div>

    {{-- Status --}}
    @if(($status ?? '') !== '')
        <div class="fi-notifications-notification fi-color-success">
            <div class="fi-notifications-notification-content">
                <div class="fi-notifications-notification-title">
                    {{ $status }}
                </div>
            </div>
        </div>
    @endif

    {{-- Info --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} flex flex-col gap-3 py-1 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-white/60">
                    Saves to <span class="font-mono text-white/80">settings.key = obsidian.about</span>
                </div>

                <div class="obs-pill">
                    Image folder:
                    <code>/public/{{ $imgBase }}/</code>
                </div>
            </div>

            <div class="{{ $content }} pt-0">
                <div class="obs-hint">
                    Upload images to <span class="font-mono">/var/www/paymenter-dev/public/{{ $imgBase }}/</span>
                    then type the <b>filename</b> here (example: <span class="font-mono">hero.webp</span>).
                </div>
            </div>
        </div>
    </div>

    {{-- SEO --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="fi-section-header-heading">SEO</div>
            <div class="fi-section-header-description">Controls the About page title and meta description.</div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }}">
                <div class="{{ $row2 }}">
                    <div class="{{ $field }}">
                        <div class="{{ $label }}">Title</div>
                        <div class="{{ $wrap }}">
                            <input type="text" wire:model.live="cfg.seo.title" class="{{ $input }}">
                        </div>
                    </div>

                    <div class="{{ $field }}">
                        <div class="{{ $label }}">Description</div>
                        <div class="{{ $wrap }}">
                            <input type="text" wire:model.live="cfg.seo.description" class="{{ $input }}">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- HERO --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="flex w-full items-start justify-between gap-4">
                <div>
                    <div class="fi-section-header-heading">Hero</div>
                    <div class="fi-section-header-description">Top header area of the About page.</div>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('cfg.hero.enabled')"
                    class="obs-toggle {{ (bool) data_get($cfg, 'hero.enabled') ? $toggleOn : $toggleOff }}"
                    aria-pressed="{{ (bool) data_get($cfg, 'hero.enabled') ? 'true' : 'false' }}"
                >
                    {{ (bool) data_get($cfg, 'hero.enabled') ? 'Enabled' : 'Disabled' }}
                </button>
            </div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} space-y-5">
                <div class="{{ $row2 }}">
                    <div class="{{ $field }}">
                        <div class="{{ $label }}">Title</div>
                        <div class="{{ $wrap }}">
                            <input type="text" wire:model.live="cfg.hero.title" class="{{ $input }}">
                        </div>
                    </div>

                    <div class="{{ $field }}">
                        <div class="{{ $label }}">Subtitle</div>
                        <div class="{{ $wrap }}">
                            <input type="text" wire:model.live="cfg.hero.subtitle" class="{{ $input }}">
                        </div>
                    </div>
                </div>

                <div class="{{ $row2 }}">
                    <div class="{{ $field }}">
                        <div class="{{ $label }}">Hero Image Filename</div>

                        <div class="{{ $wrap }}">
                            <input
                                type="text"
                                wire:model.live="cfg.hero.image.file"
                                placeholder="e.g. hero.webp"
                                class="{{ $input }}"
                            >
                        </div>

                        <div class="obs-hint">
                            File must exist at <span class="font-mono">/public/{{ $imgBase }}/</span>.
                            Only the filename is needed (no folders).
                        </div>

                        <div class="mt-3 obs-preview">
                            @if($heroPreview !== '')
                                <img src="{{ $heroPreview }}" alt="Hero preview" onerror="this.closest('.obs-preview').innerHTML='<div class=&quot;obs-preview-empty&quot;>Preview failed to load. Check the filename and location.</div>';">
                            @else
                                <div class="obs-preview-empty">No hero image set.</div>
                            @endif
                        </div>

                        <div class="mt-2 flex items-center justify-between gap-3">
                            @if($heroFile !== '')
                                <div class="obs-note">
                                    URL: <span class="font-mono">{{ $heroPreview }}</span>
                                </div>

                                <button
                                    type="button"
                                    wire:click="removeHeroImage"
                                    class="text-xs text-danger-300 hover:underline"
                                >
                                    Clear filename
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="{{ $field }}">
                        <div class="{{ $label }}">Hero Image Alt</div>
                        <div class="{{ $wrap }}">
                            <input type="text" wire:model.live="cfg.hero.image.alt" class="{{ $input }}">
                        </div>
                    </div>
                </div>

                {{-- HERO IMAGE CONTROLS --}}
                <div class="{{ $inner }} p-4 space-y-4">
                    <div class="font-medium text-white/90">Hero image display</div>

                    <div class="{{ $row2 }}">
                        <div class="{{ $field }}">
                            <div class="{{ $label }}">Fit mode</div>
                            <div class="{{ $wrap }}">
                                <select wire:model.live="cfg.hero.image.fit" class="{{ $select }}">
                                    <option value="cover">Cover (fills, crops)</option>
                                    <option value="contain">Contain (no crop)</option>
                                </select>
                            </div>
                            <div class="obs-hint">Cover crops to fill the box. Contain shows the full image.</div>
                        </div>

                        <div class="{{ $field }}">
                            <div class="{{ $label }}">Zoom</div>
                            <div class="obs-range-row mt-2">
                                <input
                                    class="obs-range"
                                    type="range"
                                    min="0.5"
                                    max="3"
                                    step="0.05"
                                    wire:model.live="cfg.hero.image.zoom"
                                />
                                <span class="obs-chip">
                                    @php $hz = (float) data_get($cfg, 'hero.image.zoom', 1); @endphp
                                    {{ number_format($hz, 2) }}x
                                </span>
                            </div>
                            <div class="obs-hint">1.00x = normal. Lower shows more, higher crops more.</div>
                        </div>
                    </div>
                </div>

                <div class="{{ $row2 }}">
                    <div class="{{ $inner }} p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-white/90">Primary CTA</div>

                            <button
                                type="button"
                                wire:click="$toggle('cfg.hero.cta_primary.enabled')"
                                class="obs-toggle {{ (bool) data_get($cfg, 'hero.cta_primary.enabled') ? $toggleOn : $toggleOff }}"
                                aria-pressed="{{ (bool) data_get($cfg, 'hero.cta_primary.enabled') ? 'true' : 'false' }}"
                            >
                                {{ (bool) data_get($cfg, 'hero.cta_primary.enabled') ? 'Enabled' : 'Disabled' }}
                            </button>
                        </div>

                        <div class="mt-3 space-y-3">
                            <div class="{{ $wrap }}">
                                <input type="text" wire:model.live="cfg.hero.cta_primary.label" placeholder="Label" class="{{ $input }}">
                            </div>
                            <div class="{{ $wrap }}">
                                <input type="text" wire:model.live="cfg.hero.cta_primary.url" placeholder="URL" class="{{ $input }}">
                            </div>
                        </div>
                    </div>

                    <div class="{{ $inner }} p-4">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-white/90">Secondary CTA</div>

                            <button
                                type="button"
                                wire:click="$toggle('cfg.hero.cta_secondary.enabled')"
                                class="obs-toggle {{ (bool) data_get($cfg, 'hero.cta_secondary.enabled') ? $toggleOn : $toggleOff }}"
                                aria-pressed="{{ (bool) data_get($cfg, 'hero.cta_secondary.enabled') ? 'true' : 'false' }}"
                            >
                                {{ (bool) data_get($cfg, 'hero.cta_secondary.enabled') ? 'Enabled' : 'Disabled' }}
                            </button>
                        </div>

                        <div class="mt-3 space-y-3">
                            <div class="{{ $wrap }}">
                                <input type="text" wire:model.live="cfg.hero.cta_secondary.label" placeholder="Label" class="{{ $input }}">
                            </div>
                            <div class="{{ $wrap }}">
                                <input type="text" wire:model.live="cfg.hero.cta_secondary.url" placeholder="URL" class="{{ $input }}">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    {{-- STATS --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="flex w-full items-start justify-between gap-4">
                <div>
                    <div class="fi-section-header-heading">Stats</div>
                    <div class="fi-section-header-description">Quick highlight numbers shown on the page.</div>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('cfg.stats_enabled')"
                    class="obs-toggle {{ (bool) data_get($cfg, 'stats_enabled') ? $toggleOn : $toggleOff }}"
                    aria-pressed="{{ (bool) data_get($cfg, 'stats_enabled') ? 'true' : 'false' }}"
                >
                    {{ (bool) data_get($cfg, 'stats_enabled') ? 'Enabled' : 'Disabled' }}
                </button>
            </div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} space-y-4">
                <div>
                    <button type="button" wire:click="addStat" class="{{ $btn }}">Add stat</button>
                </div>

                <div class="{{ $row2 }}">
                    @foreach((array) ($cfg['stats'] ?? []) as $i => $s)
                        <div class="{{ $inner }} p-4 space-y-3" wire:key="stat-{{ $i }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-white/90">Stat #{{ $i + 1 }}</div>
                                <button type="button" wire:click="removeStat({{ $i }})" class="{{ $btnDanger }}">Remove</button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.stats.{{ $i }}.value" placeholder="Value" class="{{ $input }}">
                                </div>
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.stats.{{ $i }}.label" placeholder="Label" class="{{ $input }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- STORY --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="flex w-full items-start justify-between gap-4">
                <div>
                    <div class="fi-section-header-heading">Story</div>
                    <div class="fi-section-header-description">Main Our story section text.</div>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('cfg.story.enabled')"
                    class="obs-toggle {{ (bool) data_get($cfg, 'story.enabled') ? $toggleOn : $toggleOff }}"
                    aria-pressed="{{ (bool) data_get($cfg, 'story.enabled') ? 'true' : 'false' }}"
                >
                    {{ (bool) data_get($cfg, 'story.enabled') ? 'Enabled' : 'Disabled' }}
                </button>
            </div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} space-y-4">
                <div class="{{ $field }}">
                    <div class="{{ $label }}">Title</div>
                    <div class="{{ $wrap }}">
                        <input type="text" wire:model.live="cfg.story.title" class="{{ $input }}">
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <div class="{{ $label }}">Paragraphs</div>
                    <button type="button" wire:click="addStoryParagraph" class="{{ $btn }}">Add paragraph</button>
                </div>

                <div class="space-y-3">
                    @foreach((array) ($cfg['story']['paragraphs'] ?? []) as $i => $p)
                        <div class="flex flex-col gap-2 sm:flex-row" wire:key="story-par-{{ $i }}">
                            <div class="flex-1 {{ $wrap }}">
                                <input type="text" wire:model.live="cfg.story.paragraphs.{{ $i }}" class="{{ $input }}">
                            </div>
                            <button type="button" wire:click="removeStoryParagraph({{ $i }})" class="{{ $btnDanger }}">Remove</button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- VALUES --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="flex w-full items-start justify-between gap-4">
                <div>
                    <div class="fi-section-header-heading">Values</div>
                    <div class="fi-section-header-description">What your company stands for.</div>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('cfg.values.enabled')"
                    class="obs-toggle {{ (bool) data_get($cfg, 'values.enabled') ? $toggleOn : $toggleOff }}"
                    aria-pressed="{{ (bool) data_get($cfg, 'values.enabled') ? 'true' : 'false' }}"
                >
                    {{ (bool) data_get($cfg, 'values.enabled') ? 'Enabled' : 'Disabled' }}
                </button>
            </div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} space-y-4">
                <div class="{{ $field }}">
                    <div class="{{ $label }}">Title</div>
                    <div class="{{ $wrap }}">
                        <input type="text" wire:model.live="cfg.values.title" class="{{ $input }}">
                    </div>
                </div>

                <button type="button" wire:click="addValueItem" class="{{ $btn }}">Add value</button>

                <div class="{{ $row2 }}">
                    @foreach((array) ($cfg['values']['items'] ?? []) as $i => $it)
                        <div class="{{ $inner }} p-4 space-y-3" wire:key="value-{{ $i }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-white/90">Value #{{ $i + 1 }}</div>
                                <button type="button" wire:click="removeValueItem({{ $i }})" class="{{ $btnDanger }}">Remove</button>
                            </div>

                            <div class="space-y-3">
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.values.items.{{ $i }}.title" placeholder="Title" class="{{ $input }}">
                                </div>
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.values.items.{{ $i }}.text" placeholder="Text" class="{{ $input }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- TEAM --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="flex w-full items-start justify-between gap-4">
                <div>
                    <div class="fi-section-header-heading">Team</div>
                    <div class="fi-section-header-description">Team members shown on the About page.</div>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('cfg.team.enabled')"
                    class="obs-toggle {{ (bool) data_get($cfg, 'team.enabled') ? $toggleOn : $toggleOff }}"
                    aria-pressed="{{ (bool) data_get($cfg, 'team.enabled') ? 'true' : 'false' }}"
                >
                    {{ (bool) data_get($cfg, 'team.enabled') ? 'Enabled' : 'Disabled' }}
                </button>
            </div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} space-y-4">
                <div class="{{ $field }}">
                    <div class="{{ $label }}">Title</div>
                    <div class="{{ $wrap }}">
                        <input type="text" wire:model.live="cfg.team.title" class="{{ $input }}">
                    </div>
                </div>

                <button type="button" wire:click="addTeamMember" class="{{ $btn }}">Add member</button>

                <div class="{{ $row2 }}">
                    @foreach((array) ($cfg['team']['members'] ?? []) as $i => $m)
                        @php
                            $teamFile = (string) data_get($cfg, "team.members.$i.image_file", '');
                            $teamPreview = $previewUrl($teamFile);
                        @endphp

                        <div class="{{ $inner }} p-4 space-y-4" wire:key="team-member-{{ $i }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-white/90">Member #{{ $i + 1 }}</div>
                                <button type="button" wire:click="removeTeamMember({{ $i }})" class="{{ $btnDanger }}">Remove</button>
                            </div>

                            <div class="space-y-3">
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.team.members.{{ $i }}.name" placeholder="Name" class="{{ $input }}">
                                </div>
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.team.members.{{ $i }}.role" placeholder="Role" class="{{ $input }}">
                                </div>

                                {{-- Filename --}}
                                <div class="{{ $field }}">
                                    <div class="{{ $label }}">Photo Filename</div>

                                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:items-center">
                                        <div class="{{ $wrap }} sm:col-span-2">
                                            <input
                                                type="text"
                                                wire:model.live="cfg.team.members.{{ $i }}.image_file"
                                                placeholder="e.g. team-pj.webp"
                                                class="{{ $input }}"
                                            >
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                wire:click="removeTeamMemberImage({{ $i }})"
                                                class="{{ $btnDanger }}"
                                            >
                                                Clear
                                            </button>
                                        </div>
                                    </div>

                                    <div class="obs-hint">
                                        Looks in <span class="font-mono">/public/{{ $imgBase }}/</span>.
                                        Only filename â€” no folders.
                                    </div>

                                    <div class="mt-3 obs-preview">
                                        @if($teamPreview !== '')
                                            <img src="{{ $teamPreview }}" alt="Team preview" onerror="this.closest('.obs-preview').innerHTML='<div class=&quot;obs-preview-empty&quot;>Preview failed to load. Check the filename and location.</div>';">
                                        @else
                                            <div class="obs-preview-empty">No photo set.</div>
                                        @endif
                                    </div>

                                    @if($teamFile !== '')
                                        <div class="obs-note mt-2">
                                            URL: <span class="font-mono">{{ $teamPreview }}</span>
                                        </div>
                                    @endif
                                </div>

                                {{-- TEAM IMAGE CONTROLS --}}
                                <div class="{{ $inner }} p-4 space-y-4">
                                    <div class="font-medium text-white/90">Image display</div>

                                    <div class="{{ $row2 }}">
                                        <div class="{{ $field }}">
                                            <div class="{{ $label }}">Fit mode</div>
                                            <div class="{{ $wrap }}">
                                                <select wire:model.live="cfg.team.members.{{ $i }}.image_fit" class="{{ $select }}">
                                                    <option value="cover">Cover (fills, crops)</option>
                                                    <option value="contain">Contain (no crop)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="{{ $field }}">
                                            <div class="{{ $label }}">Zoom</div>
                                            <div class="obs-range-row mt-2">
                                                <input
                                                    class="obs-range"
                                                    type="range"
                                                    min="0.5"
                                                    max="3"
                                                    step="0.05"
                                                    wire:model.live="cfg.team.members.{{ $i }}.image_zoom"
                                                />
                                                <span class="obs-chip">
                                                    @php $tz = (float) data_get($cfg, "team.members.$i.image_zoom", 1); @endphp
                                                    {{ number_format($tz, 2) }}x
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {{-- Legacy URL fallback (kept, optional) --}}
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.team.members.{{ $i }}.image" placeholder="Legacy Image URL (optional)" class="{{ $input }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- TIMELINE --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="flex w-full items-start justify-between gap-4">
                <div>
                    <div class="fi-section-header-heading">Timeline</div>
                    <div class="fi-section-header-description">Milestones in your company history.</div>
                </div>

                <button
                    type="button"
                    wire:click="$toggle('cfg.timeline.enabled')"
                    class="obs-toggle {{ (bool) data_get($cfg, 'timeline.enabled') ? $toggleOn : $toggleOff }}"
                    aria-pressed="{{ (bool) data_get($cfg, 'timeline.enabled') ? 'true' : 'false' }}"
                >
                    {{ (bool) data_get($cfg, 'timeline.enabled') ? 'Enabled' : 'Disabled' }}
                </button>
            </div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} space-y-4">
                <div class="{{ $field }}">
                    <div class="{{ $label }}">Title</div>
                    <div class="{{ $wrap }}">
                        <input type="text" wire:model.live="cfg.timeline.title" class="{{ $input }}">
                    </div>
                </div>

                <button type="button" wire:click="addTimelineItem" class="{{ $btn }}">Add milestone</button>

                <div class="space-y-3">
                    @foreach((array) ($cfg['timeline']['items'] ?? []) as $i => $t)
                        <div class="{{ $inner }} p-4 space-y-3" wire:key="timeline-{{ $i }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-white/90">Milestone #{{ $i + 1 }}</div>
                                <button type="button" wire:click="removeTimelineItem({{ $i }})" class="{{ $btnDanger }}">Remove</button>
                            </div>

                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.timeline.items.{{ $i }}.year" placeholder="Year" class="{{ $input }}">
                                </div>
                                <div class="{{ $wrap }} md:col-span-2">
                                    <input type="text" wire:model.live="cfg.timeline.items.{{ $i }}.title" placeholder="Title" class="{{ $input }}">
                                </div>
                            </div>

                            <div class="{{ $wrap }}">
                                <input type="text" wire:model.live="cfg.timeline.items.{{ $i }}.text" placeholder="Text" class="{{ $input }}">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- GALLERY --}}
    <div class="{{ $section }} {{ $box }}">
        <div class="fi-section-header">
            <div class="flex w-full items-start justify-between gap-4">
                <div>
                    <div class="fi-section-header-heading">Gallery</div>
                    <div class="fi-section-header-description">Images displayed on the About page.</div>
                </div>

                {{-- FIXED: broken markup here --}}
                <button
                    type="button"
                    wire:click="$toggle('cfg.gallery.enabled')"
                    class="obs-toggle {{ (bool) data_get($cfg, 'gallery.enabled') ? $toggleOn : $toggleOff }}"
                    aria-pressed="{{ (bool) data_get($cfg, 'gallery.enabled') ? 'true' : 'false' }}"
                >
                    {{ (bool) data_get($cfg, 'gallery.enabled') ? 'Enabled' : 'Disabled' }}
                </button>
            </div>
        </div>

        <div class="{{ $contentCtn }}">
            <div class="{{ $content }} space-y-4">
                <div class="{{ $field }}">
                    <div class="{{ $label }}">Title</div>
                    <div class="{{ $wrap }}">
                        <input type="text" wire:model.live="cfg.gallery.title" class="{{ $input }}">
                    </div>
                </div>

                <button type="button" wire:click="addGalleryImage" class="{{ $btn }}">Add image</button>

                <div class="{{ $row2 }}">
                    @foreach((array) ($cfg['gallery']['images'] ?? []) as $i => $g)
                        @php
                            $gFile = (string) data_get($cfg, "gallery.images.$i.file", '');
                            $gPreview = $previewUrl($gFile);
                        @endphp

                        <div class="{{ $inner }} p-4 space-y-4" wire:key="gallery-img-{{ $i }}">
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium text-white/90">Image #{{ $i + 1 }}</div>
                                <button type="button" wire:click="removeGalleryImage({{ $i }})" class="{{ $btnDanger }}">Remove</button>
                            </div>

                            {{-- Filename --}}
                            <div class="{{ $field }}">
                                <div class="{{ $label }}">Image Filename</div>

                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 sm:items-center">
                                    <div class="{{ $wrap }} sm:col-span-2">
                                        <input
                                            type="text"
                                            wire:model.live="cfg.gallery.images.{{ $i }}.file"
                                            placeholder="e.g. gallery-1.webp"
                                            class="{{ $input }}"
                                        >
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            wire:click="removeGalleryImageFile({{ $i }})"
                                            class="{{ $btnDanger }}"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                </div>

                                <div class="obs-hint">
                                    Looks in <span class="font-mono">/public/{{ $imgBase }}/</span>.
                                    Only filename â€” no folders.
                                </div>

                                <div class="mt-3 obs-preview">
                                    @if($gPreview !== '')
                                        <img src="{{ $gPreview }}" alt="Gallery preview" onerror="this.closest('.obs-preview').innerHTML='<div class=&quot;obs-preview-empty&quot;>Preview failed to load. Check the filename and location.</div>';">
                                    @else
                                        <div class="obs-preview-empty">No image set.</div>
                                    @endif
                                </div>

                                @if($gFile !== '')
                                    <div class="obs-note mt-2">
                                        URL: <span class="font-mono">{{ $gPreview }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- GALLERY IMAGE CONTROLS --}}
                            <div class="{{ $inner }} p-4 space-y-4">
                                <div class="font-medium text-white/90">Image display</div>

                                <div class="{{ $row2 }}">
                                    <div class="{{ $field }}">
                                        <div class="{{ $label }}">Fit mode</div>
                                        <div class="{{ $wrap }}">
                                            <select wire:model.live="cfg.gallery.images.{{ $i }}.fit" class="{{ $select }}">
                                                <option value="cover">Cover (fills, crops)</option>
                                                <option value="contain">Contain (no crop)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="{{ $field }}">
                                        <div class="{{ $label }}">Zoom</div>
                                        <div class="obs-range-row mt-2">
                                            <input
                                                class="obs-range"
                                                type="range"
                                                min="0.5"
                                                max="3"
                                                step="0.05"
                                                wire:model.live="cfg.gallery.images.{{ $i }}.zoom"
                                            />
                                            <span class="obs-chip">
                                                @php $gz = (float) data_get($cfg, "gallery.images.$i.zoom", 1); @endphp
                                                {{ number_format($gz, 2) }}x
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3">
                                {{-- legacy optional URL still here --}}
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.gallery.images.{{ $i }}.url" placeholder="Legacy Image URL (optional)" class="{{ $input }}">
                                </div>
                                <div class="{{ $wrap }}">
                                    <input type="text" wire:model.live="cfg.gallery.images.{{ $i }}.alt" placeholder="Alt text" class="{{ $input }}">
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>
        </div>
    </div>
</div>
