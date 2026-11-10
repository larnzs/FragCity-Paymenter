<?php

namespace Paymenter\Extensions\Others\ObsidianFooterEditor;

use App\Classes\Extension\Attributes\ExtensionMeta;
use App\Classes\Extension\Extension;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Paymenter\Extensions\Others\ObsidianFooterEditor\Livewire\FooterEditor;

#[ExtensionMeta(
    name: 'Obsidian Footer Editor',
    description: 'Footer editor for the Obsidian theme.',
    author: 'TamelessHosting',
    version: '1.0.0'
)]
class ObsidianFooterEditor extends Extension
{
    public function boot(): void
    {
        $viewsPath = base_path('extensions/Others/ObsidianFooterEditor/Resources/views');

        // Blade namespace: obsidian-footer-editor::...
        View::addNamespace('obsidian-footer-editor', $viewsPath);

        // Livewire component alias used by @livewire(...)
        Livewire::component('obsidian-footer-editor.footer-editor', FooterEditor::class);
    }
}
