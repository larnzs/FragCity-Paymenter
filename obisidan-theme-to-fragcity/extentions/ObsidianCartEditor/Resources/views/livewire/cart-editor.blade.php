{{-- extensions/Others/ObsidianCartEditor/Resources/views/livewire/cart-editor.blade.php --}}

<div>
    <div class="one-header">
        <div>
            <h1 class="one-title">Cart Editor</h1>
            <div class="one-sub">
                Edit cart text and toggle sections (no upsells, no add-ons).
            </div>
        </div>

        <div class="one-actions">
            <button type="button" class="one-btn one-btn-danger" wire:click="resetToDefaults">
                Reset
            </button>
            <button type="button" class="one-btn one-btn-primary" wire:click="save">
                Save
            </button>
        </div>
    </div>

    @if(!empty($status))
        <div class="one-status">
            {{ $status }}
        </div>
    @endif

    <div class="one-grid">

        {{-- Header --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Header</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.header.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Controls the main cart heading area.</div>

            <div class="one-divider"></div>

            <div class="one-field">
                <label class="one-label-sm">Title</label>
                <input class="one-input" type="text" wire:model="cfg.header.title" placeholder="Shopping Cart">
            </div>

            <div class="one-field">
                <label class="one-label-sm">Subtitle</label>
                <input class="one-input" type="text" wire:model="cfg.header.subtitle" placeholder="Review your order and proceed to checkout">
            </div>
        </div>

        {{-- Stepper --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Stepper</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.stepper.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Controls the progress steps above the cart.</div>

            <div class="one-divider"></div>

            <div class="one-field">
                <label class="one-label-sm">Step 1 label</label>
                <input class="one-input" type="text" wire:model="cfg.stepper.step1" placeholder="Review Cart">
            </div>

            <div class="one-field">
                <label class="one-label-sm">Step 2 label</label>
                <input class="one-input" type="text" wire:model="cfg.stepper.step2" placeholder="Checkout">
            </div>

            <div class="one-field">
                <label class="one-label-sm">Step 3 label</label>
                <input class="one-input" type="text" wire:model="cfg.stepper.step3" placeholder="Complete">
            </div>
        </div>

        {{-- Empty cart --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Empty Cart Box</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.empty.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Shown when the cart has no items.</div>

            <div class="one-divider"></div>

            <div class="one-field">
                <label class="one-label-sm">Title</label>
                <input class="one-input" type="text" wire:model="cfg.empty.title" placeholder="Your cart is empty">
            </div>

            <div class="one-field">
                <label class="one-label-sm">Subtitle</label>
                <input class="one-input" type="text" wire:model="cfg.empty.subtitle" placeholder="Add a product to get started.">
            </div>
        </div>

        {{-- Summary --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Order Summary</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.summary.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Controls summary card labels and button text.</div>

            <div class="one-divider"></div>

            <div class="one-field">
                <label class="one-label-sm">Title</label>
                <input class="one-input" type="text" wire:model="cfg.summary.title" placeholder="Order Summary">
            </div>

            <div class="one-row2">
                <div class="one-field">
                    <label class="one-label-sm">Coupon label</label>
                    <input class="one-input" type="text" wire:model="cfg.summary.coupon_label" placeholder="Have a coupon code?">
                </div>
                <div class="one-field">
                    <label class="one-label-sm">Coupon placeholder</label>
                    <input class="one-input" type="text" wire:model="cfg.summary.coupon_placeholder" placeholder="Enter code">
                </div>
            </div>

            <div class="one-row2">
                <div class="one-field">
                    <label class="one-label-sm">Apply button text</label>
                    <input class="one-input" type="text" wire:model="cfg.summary.coupon_apply" placeholder="Apply">
                </div>
                <div class="one-field">
                    <label class="one-label-sm">Checkout button text</label>
                    <input class="one-input" type="text" wire:model="cfg.summary.cta" placeholder="Proceed to Checkout">
                </div>
            </div>

            <div class="one-field">
                <label class="one-label-sm">Stripe footer note</label>
                <input class="one-input" type="text" wire:model="cfg.summary.stripe_note" placeholder="Secure payment powered by Stripe">
            </div>
        </div>

        {{-- Continue shopping --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Continue Shopping Link</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.continue_shopping.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Controls the “Continue Shopping” link label.</div>

            <div class="one-divider"></div>

            <div class="one-field">
                <label class="one-label-sm">Label</label>
                <input class="one-input" type="text" wire:model="cfg.continue_shopping.label" placeholder="Continue Shopping">
            </div>
        </div>

        {{-- TOS --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Terms Checkbox Section</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.tos.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">
                Note: this still requires a TOS URL in core settings (<code style="color: rgba(255,255,255,0.75);">config('settings.tos')</code>).
            </div>
        </div>

        {{-- Trust bullets --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Trust Bullets</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.trust.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Short trust statements shown in the cart UI.</div>

            <div class="one-divider"></div>

            @foreach(($cfg['trust']['items'] ?? []) as $i => $t)
                <div class="one-field">
                    <label class="one-label-sm">Item #{{ $i + 1 }}</label>
                    <input class="one-input" type="text" wire:model="cfg.trust.items.{{ $i }}">
                </div>
            @endforeach
        </div>

        {{-- Why choose us --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Why Choose Us</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.why.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Controls the feature list section in the cart.</div>

            <div class="one-divider"></div>

            <div class="one-field">
                <label class="one-label-sm">Title</label>
                <input class="one-input" type="text" wire:model="cfg.why.title" placeholder="Why Choose Us?">
            </div>

            @foreach(($cfg['why']['items'] ?? []) as $i => $wi)
                <div class="one-section-box">
                    <div class="one-section-head">
                        <div>
                            <div style="font-weight:900; color:#fff;">Item #{{ $i + 1 }}</div>
                            <div class="one-hint">Title + subtitle shown in the cart UI.</div>
                        </div>
                    </div>

                    <div class="one-row2">
                        <div class="one-field">
                            <label class="one-label-sm">Title</label>
                            <input class="one-input" type="text" wire:model="cfg.why.items.{{ $i }}.title">
                        </div>
                        <div class="one-field">
                            <label class="one-label-sm">Subtitle</label>
                            <input class="one-input" type="text" wire:model="cfg.why.items.{{ $i }}.subtitle">
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Help card --}}
        <div class="one-card">
            <div class="one-card-title-row">
                <h3 class="one-card-title">Help Card</h3>
                <label class="one-check">
                    <input type="checkbox" wire:model="cfg.help.enabled">
                    Enabled
                </label>
            </div>
            <div class="one-card-sub">Controls the support/help box.</div>

            <div class="one-divider"></div>

            <div class="one-field">
                <label class="one-label-sm">Title</label>
                <input class="one-input" type="text" wire:model="cfg.help.title" placeholder="Need Help?">
            </div>

            <div class="one-field">
                <label class="one-label-sm">Subtitle</label>
                <input class="one-input" type="text" wire:model="cfg.help.subtitle">
            </div>

            <div class="one-row2">
                <div class="one-field">
                    <label class="one-label-sm">Link label</label>
                    <input class="one-input" type="text" wire:model="cfg.help.link_label" placeholder="Chat with us">
                </div>
                <div class="one-field">
                    <label class="one-label-sm">Link URL</label>
                    <input class="one-input" type="text" wire:model="cfg.help.link_url" placeholder="#">
                </div>
            </div>
        </div>

    </div>
</div>
