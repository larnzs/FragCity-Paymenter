<?php
// extensions/Others/ObsidianAboutPage/ObsidianAboutPage.php

namespace Paymenter\Extensions\Others\ObsidianAboutPage;

use App\Classes\Extension\Extension;
use App\Classes\Extension\Attributes\ExtensionMeta;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

#[ExtensionMeta(
    name: 'Obsidian About Page',
    description: 'Adds a customizable About page (theme-aware) and a Filament editor.',
    author: 'TamelessHosting',
    version: '1.0.0'
)]
class ObsidianAboutPage extends Extension
{
    public function boot(): void
    {
        $base = base_path('extensions/Others/ObsidianAboutPage/Resources/views');
        View::addNamespace('obsidian-about-page', $base);

        // Livewire component registration
        if (class_exists(\Paymenter\Extensions\Others\ObsidianAboutPage\Livewire\AboutPageEditor::class)) {
            Livewire::component(
                'obsidian-about-page.editor',
                \Paymenter\Extensions\Others\ObsidianAboutPage\Livewire\AboutPageEditor::class
            );
        }

        // Routes
        if (!Route::has('obsidian.about.show')) {
            require base_path('extensions/Others/ObsidianAboutPage/routes/web.php');
        }
    }

    public function getConfig($values = [])
    {
        return [];
    }
}
