<?php

namespace Paymenter\Extensions\Others\ObsidianFooterEditor\Admin\Pages;

use Filament\Pages\Page;
use Paymenter\Extensions\Others\ObsidianFooterEditor\Livewire\FooterEditor;

class Footer extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Obsidian Theme';
    protected static ?string $navigationLabel = 'Footer';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-group';
    protected static ?string $title = 'Footer';
    protected static ?string $slug = 'obsidian-theme/footer';

    public function mount(): void
    {
        // Register BOTH names here too (guaranteed to run for this route).
        $lw = app('livewire');

        if (is_object($lw) && method_exists($lw, 'component')) {
            $lw->component('obsidian-footer-editor.footer-editor', FooterEditor::class);
            $lw->component('obsidian-footer-editor-footer-editor', FooterEditor::class);
        }
    }

    public function getView(): string
    {
        return 'obsidian-footer-editor::admin.footer';
    }
}
