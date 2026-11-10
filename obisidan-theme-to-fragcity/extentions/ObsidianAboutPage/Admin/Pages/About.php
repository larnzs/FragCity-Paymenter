<?php

namespace Paymenter\Extensions\Others\ObsidianAboutPage\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class About extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Obsidian Theme';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-information-circle';
    protected static ?string $navigationLabel = 'About Page';

    protected static ?string $slug = 'obsidian-theme/about';

    public function getView(): string
    {
        return 'obsidian-about-page::admin.about';
    }
}
