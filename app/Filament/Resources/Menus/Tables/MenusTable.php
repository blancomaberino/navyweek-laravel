<?php

declare(strict_types=1);

namespace App\Filament\Resources\Menus\Tables;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Filament\Support\EnumOptions;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The menu registry — the header, footer columns and legal row. Drag-reorderable
 * on `sort_order` so an editor can reorder the footer columns directly.
 */
class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('key')
                    ->searchable()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('location')
                    ->badge()
                    ->formatStateUsing(fn (MenuLocation $state): string => $state->label()),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Links')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('location')
                    ->options(EnumOptions::map(MenuLocation::cases())),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
