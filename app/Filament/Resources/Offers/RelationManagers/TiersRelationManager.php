<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The offer's savings tiers (audience → amount), ordered by `sort_order`. A
 * keyless child of the offer; edited inline here.
 */
class TiersRelationManager extends RelationManager
{
    protected static string $relationship = 'tiers';

    protected static ?string $title = 'Savings tiers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('audience')->required()->maxLength(255),
            TextInput::make('amount')->required()->maxLength(255)
                ->helperText('e.g. "20% off" or "$50".'),
            TextInput::make('note')->maxLength(255),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('audience'),
                TextColumn::make('amount'),
                TextColumn::make('note')->limit(40)->toggleable(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
