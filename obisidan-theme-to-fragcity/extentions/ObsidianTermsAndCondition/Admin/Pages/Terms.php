<?php

namespace Paymenter\Extensions\Others\ObsidianTermsAndCondition\Admin\Pages;

use BackedEnum;
use Filament\Pages\Page;
use UnitEnum;

class Terms extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Obsidian Theme';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Terms & Conditions';

    protected static ?string $slug = 'obsidian-theme/terms';

    public function getView(): string
    {
        return 'obsidian-terms-and-condition::admin.terms';
    }
}
