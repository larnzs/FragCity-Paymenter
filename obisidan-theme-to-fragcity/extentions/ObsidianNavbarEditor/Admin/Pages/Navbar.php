<?php

namespace Paymenter\Extensions\Others\ObsidianNavbarEditor\Admin\Pages;

use Filament\Pages\Page;
use Paymenter\Extensions\Others\ObsidianNavbarEditor\Livewire\NavbarEditor;

class Navbar extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Obsidian Theme';
    protected static ?string $navigationLabel = 'Navbar';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';
    protected static ?string $title = 'Navbar';
    protected static ?string $slug = 'obsidian-theme/navbar';

    public function mount(): void
    {
        // Register BOTH names here too (guaranteed to run for this route).
        $lw = app('livewire');

        if (is_object($lw) && method_exists($lw, 'component')) {
            $lw->component('obsidian-navbar-editor.navbar-editor', NavbarEditor::class);
            $lw->component('obsidian-navbar-editor-navbar-editor', NavbarEditor::class);
        }
    }

    public function getView(): string
    {
        return 'obsidian-navbar-editor::admin.navbar';
    }
}
