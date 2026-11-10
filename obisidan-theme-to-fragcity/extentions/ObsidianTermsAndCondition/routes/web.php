<?php
// extensions/Others/ObsidianTermsAndCondition/routes/web.php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::middleware(['web'])->group(function () {
    Route::get('/terms', function () {
        $raw = DB::table('settings')->where('key', 'obsidian.terms')->value('value');

        $cfg = null;

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $cfg = $decoded;
            }
        }

        // Defaults (guarantee the page is never blank)
        $defaults = [
            'seo' => [
                'title' => 'Terms & Conditions',
                'description' => '',
                'robots' => 'index,follow',
                'canonical_url' => '',
            ],

            'hero' => [
                'enabled' => true,
                'title' => 'Terms & Conditions',
                'summary' => 'Everything you need to know about using our services.',
                'last_updated' => [
                    'enabled' => true,
                    'label' => 'Last updated:',
                    'date' => date('Y-m-d'),
                    'display' => '',
                ],
                'actions' => [
                    'enabled' => false,
                    'buttons' => [],
                ],
            ],

            'toc' => [
                'enabled' => true,
                'title' => 'On this page',
                'mobile_collapse' => [
                    'enabled' => true,
                ],
            ],

            'sections' => [
                [
                    'enabled' => true,
                    'visible_in_toc' => true,
                    'number' => '1',
                    'id' => 'introduction',
                    'title' => 'Introduction',
                    'blocks' => [
                        [
                            'type' => 'paragraph',
                            'enabled' => true,
                            'text' => 'These Terms & Conditions govern your use of our website and services.',
                        ],
                    ],
                ],
            ],

            'footer' => [
                'enabled' => true,
                'left_text' => '© ' . date('Y') . ' TamelessHosting. All rights reserved.',
                'links' => [],
            ],
        ];

        // Merge user cfg over defaults
        $cfg = is_array($cfg) ? array_replace_recursive($defaults, $cfg) : $defaults;

        // Keep things safe
        if (!isset($cfg['sections']) || !is_array($cfg['sections'])) {
            $cfg['sections'] = $defaults['sections'];
        }

        return view('obsidian-terms-and-condition::front.terms', [
            'cfg' => $cfg,
        ]);
    })->name('obsidian.terms.show');
});
