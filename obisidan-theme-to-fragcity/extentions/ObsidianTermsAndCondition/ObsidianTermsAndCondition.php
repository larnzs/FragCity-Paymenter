<?php

namespace Paymenter\Extensions\Others\ObsidianTermsAndCondition;

use App\Classes\Extension\Extension;
use App\Classes\Extension\Attributes\ExtensionMeta;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

#[ExtensionMeta(
    name: 'Obsidian Terms & Conditions',
    description: 'Adds a customizable Terms & Conditions page (theme-aware) and a Filament editor.',
    author: 'TamelessHosting',
    version: '1.0.0'
)]
class ObsidianTermsAndCondition extends Extension
{
    public function boot(): void
    {
        $base = base_path('extensions/Others/ObsidianTermsAndCondition/Resources/views');

        /**
         * Register BOTH namespaces to prevent “No hint path defined” during the transition.
         */
        View::addNamespace('obsidian-terms-page', $base);
        View::addNamespace('obsidian-terms-and-condition', $base);

        /**
         * Register BOTH Livewire aliases to prevent breaking any blade usage.
         */
        Livewire::component(
            'obsidian-terms-page.editor',
            \Paymenter\Extensions\Others\ObsidianTermsAndCondition\Livewire\TermsPageEditor::class
        );

        Livewire::component(
            'obsidian-terms-and-condition.editor',
            \Paymenter\Extensions\Others\ObsidianTermsAndCondition\Livewire\TermsPageEditor::class
        );

        // Public route
        if (!Route::has('obsidian.terms.show')) {
            require base_path('extensions/Others/ObsidianTermsAndCondition/routes/web.php');
        }
    }

    public function getConfig($values = [])
    {
        return [];
    }
}
