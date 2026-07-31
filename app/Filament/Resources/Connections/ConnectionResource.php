<?php

declare(strict_types=1);

namespace App\Filament\Resources\Connections;

use App\Domain\Crm\Models\Connection;
use App\Filament\Resources\Connections\Pages\CreateConnection;
use App\Filament\Resources\Connections\Pages\EditConnection;
use App\Filament\Resources\Connections\Pages\ListConnections;
use App\Filament\Resources\Connections\Schemas\ConnectionForm;
use App\Filament\Resources\Connections\Tables\ConnectionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ConnectionResource extends Resource
{
    protected static ?string $model = Connection::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Connections';

    protected static ?string $recordTitleAttribute = 'brand';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    /**
     * Global/record search targets the indexed identity columns, not the whole row.
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['brand', 'slug', 'key'];
    }

    public static function form(Schema $schema): Schema
    {
        return ConnectionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ConnectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OffersRelationManager::class,
            RelationManagers\ResearchRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListConnections::route('/'),
            'create' => CreateConnection::route('/create'),
            'edit' => EditConnection::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
