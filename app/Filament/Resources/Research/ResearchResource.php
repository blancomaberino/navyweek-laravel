<?php

declare(strict_types=1);

namespace App\Filament\Resources\Research;

use App\Domain\Research\Models\Research;
use App\Filament\Resources\Research\Pages\CreateResearch;
use App\Filament\Resources\Research\Pages\EditResearch;
use App\Filament\Resources\Research\Pages\ListResearch;
use App\Filament\Resources\Research\Schemas\ResearchForm;
use App\Filament\Resources\Research\Tables\ResearchTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResearchResource extends Resource
{
    protected static ?string $model = Research::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?string $navigationLabel = 'Research';

    protected static ?string $recordTitleAttribute = 'brief_path';

    protected static string|\UnitEnum|null $navigationGroup = 'Research';

    public static function form(Schema $schema): Schema
    {
        return ResearchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResearchTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResearch::route('/'),
            'create' => CreateResearch::route('/create'),
            'edit' => EditResearch::route('/{record}/edit'),
        ];
    }
}
