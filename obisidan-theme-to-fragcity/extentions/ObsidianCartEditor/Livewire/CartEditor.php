<?php

namespace Paymenter\Extensions\Others\ObsidianCartEditor\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class CartEditor extends Component
{
    public array $cfg = [];
    public string $status = '';

    public function mount(): void
    {
        $this->cfg = $this->loadConfig();
        $this->status = '';
    }

    public function save(): void
    {
        $this->status = '';

        // Safety: ensure cfg is always an array
        if (!is_array($this->cfg)) {
            $this->cfg = self::defaultConfig();
        }

        $payload = json_encode($this->cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!is_string($payload) || $payload === '') {
            $this->status = 'Save failed: invalid JSON';
            return;
        }

        DB::table('settings')->updateOrInsert(
            ['key' => 'obsidian.cart_editor'],
            ['value' => $payload]
        );

        $this->status = 'Saved';
    }

    public function resetToDefaults(): void
    {
        $this->cfg = self::defaultConfig();
        $this->save();
        $this->status = 'Reset to defaults';
    }

    protected function loadConfig(): array
    {
        $raw = DB::table('settings')->where('key', 'obsidian.cart_editor')->value('value');

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                // Merge defaults so new keys always exist
                return array_replace_recursive(self::defaultConfig(), $decoded);
            }
        }

        return self::defaultConfig();
    }

    public static function defaultConfig(): array
    {
        return [
            'header' => [
                'enabled' => true,
                'title' => 'Shopping Cart',
                'subtitle' => 'Review your order and proceed to checkout',
            ],
            'stepper' => [
                'enabled' => true,
                'step1' => 'Review Cart',
                'step2' => 'Checkout',
                'step3' => 'Complete',
            ],
            'empty' => [
                'enabled' => true,
                'title' => 'Your cart is empty',
                'subtitle' => 'Add a product to get started.',
            ],
            'summary' => [
                'enabled' => true,
                'title' => 'Order Summary',
                'coupon_label' => 'Have a coupon code?',
                'coupon_placeholder' => 'Enter code',
                'coupon_apply' => 'Apply',
                'cta' => 'Proceed to Checkout',
                'stripe_note' => 'Secure payment powered by Stripe',
            ],
            'continue_shopping' => [
                'enabled' => true,
                'label' => 'Continue Shopping',
            ],
            'tos' => [
                'enabled' => true,
            ],
            'trust' => [
                'enabled' => true,
                'items' => [
                    'SSL Encrypted Payment',
                    '30-Day Money Back Guarantee',
                    '24/7 Support Available',
                ],
            ],
            'why' => [
                'enabled' => true,
                'title' => 'Why Choose Us?',
                'items' => [
                    ['title' => '99.9% Uptime Guarantee', 'subtitle' => 'Enterprise-grade infrastructure'],
                    ['title' => 'Instant Setup', 'subtitle' => 'Server ready in minutes'],
                    ['title' => '24/7 Expert Support', 'subtitle' => 'Always here to help'],
                ],
            ],
            'help' => [
                'enabled' => true,
                'title' => 'Need Help?',
                'subtitle' => 'Our team is available 24/7 to assist you with your purchase',
                'link_label' => 'Chat with us',
                'link_url' => '#',
            ],
        ];
    }

    public function render()
    {
        return view('obsidian-cart-editor::livewire.cart-editor');
    }
}
