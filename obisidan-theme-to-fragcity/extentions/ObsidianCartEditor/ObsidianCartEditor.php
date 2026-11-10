<?php

namespace Paymenter\Extensions\Others\ObsidianCartEditor;

use App\Classes\Extension\Extension;
use App\Classes\Extension\Attributes\ExtensionMeta;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;
use Paymenter\Extensions\Others\ObsidianCartEditor\Livewire\CartEditor as CartEditorLivewire;

#[ExtensionMeta(
    name: 'Obsidian Cart Editor',
    description: 'Edit text and toggle sections on the Obsidian cart page.',
    author: 'TamelessHosting',
    version: '1.0.0'
)]
class ObsidianCartEditor extends Extension
{
    public function boot(): void
    {
        View::addNamespace(
            'obsidian-cart-editor',
            base_path('extensions/Others/ObsidianCartEditor/Resources/views')
        );

        Livewire::component('obsidian-cart-editor.editor', CartEditorLivewire::class);
    }

    public function getConfig($values = []): array
    {
        return [];
    }
}
