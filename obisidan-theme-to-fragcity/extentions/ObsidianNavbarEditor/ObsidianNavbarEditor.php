<?php

namespace Paymenter\Extensions\Others\ObsidianNavbarEditor;

use App\Classes\Extension\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Paymenter\Extensions\Others\ObsidianNavbarEditor\Livewire\NavbarEditor;

#[ExtensionMeta(
    name: 'Obsidian Navbar Editor',
    description: 'Navbar editor for the Obsidian theme.',
    author: 'TamelessHosting',
    version: '1.0.0'
)]
class ObsidianNavbarEditor extends Extension
{
    public function boot(): void
    {
        $viewsPath = base_path('extensions/Others/ObsidianNavbarEditor/Resources/views');

        // Blade namespace: obsidian-navbar-editor::...
        View::addNamespace('obsidian-navbar-editor', $viewsPath);

        // Livewire component alias used by @livewire(...)
        Livewire::component('obsidian-navbar-editor.navbar-editor', NavbarEditor::class);
    }
}
