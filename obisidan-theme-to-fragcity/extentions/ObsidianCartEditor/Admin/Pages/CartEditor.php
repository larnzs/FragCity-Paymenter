<?php

namespace Paymenter\Extensions\Others\ObsidianCartEditor\Admin\Pages;

use Filament\Pages\Page;
use Filament\Panel;

class CartEditor extends Page
{
    protected static string|\UnitEnum|null $navigationGroup = 'Obsidian Theme';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-pencil-square';
    protected static ?string $title = 'Cart Editor';

    public static function getSlug(?Panel $panel = null): string
    {
        return 'obsidian-theme/cart-editor';
    }

    public function getView(): string
    {
        return 'obsidian-cart-editor::admin.cart';
    }
}
