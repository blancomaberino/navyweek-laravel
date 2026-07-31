<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects;

use App\Domain\Publishing\Models\Redirect;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\EditRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Resources\Redirects\Schemas\RedirectForm;
use App\Filament\Resources\Redirects\Tables\RedirectsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The redirect store — every 301 the site serves. Editors add manual rules here, and
 * the auto-301 on a page rename (ChangeUrlPathAction) writes `slug-change` rows that
 * also show up in this table. `CanonicalUrlMiddleware` consults these on every
 * request, so a row edited here is live with no deploy.
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Redirects';

    protected static ?string $recordTitleAttribute = 'from_path';

    protected static string|\UnitEnum|null $navigationGroup = 'Publishing';

    /**
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['from_path', 'to_path'];
    }

    public static function form(Schema $schema): Schema
    {
        return RedirectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RedirectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
            'create' => CreateRedirect::route('/create'),
            'edit' => EditRedirect::route('/{record}/edit'),
        ];
    }
}
