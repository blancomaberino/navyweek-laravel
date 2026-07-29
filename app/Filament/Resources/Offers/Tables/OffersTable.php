<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Tables;

use App\Domain\Catalog\Enums\OfferType;
use App\Filament\Support\EnumOptions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

/**
 * The offer list — one row per brand offer, keyed to its connection. Columns
 * surface the offer type, headline, primary/published flags, and the tier count;
 * filters narrow by type, connection, and the primary/published flags.
 */
class OffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('connection.brand')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('offer_type')
                    ->badge()
                    ->formatStateUsing(fn (OfferType $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('headline_discount')
                    ->limit(40)
                    ->toggleable(),
                IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),
                TextColumn::make('tiers_count')
                    ->counts('tiers')
                    ->label('Tiers')
                    ->toggleable(),
                TextColumn::make('sort_order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('offer_type')
                    ->options(EnumOptions::map(OfferType::cases())),
                SelectFilter::make('connection')
                    ->relationship('connection', 'brand')
                    ->searchable()
                    ->preload(false),
                TernaryFilter::make('is_primary')->label('Primary'),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
