<?php

declare(strict_types=1);

namespace App\Filament\Resources\Research\Tables;

use App\Domain\Research\Enums\ResearchedBy;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Models\Research;
use App\Filament\Support\EnumOptions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The research-brief registry — one row per (connection, version). Surfaces the
 * brand, version, status, provenance and last-verified date; a boolean marks
 * whether the verbatim `raw_markdown` is present.
 */
class ResearchTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_verified', 'desc')
            ->columns([
                TextColumn::make('connection.brand')
                    ->label('Brand')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('version')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ResearchStatus $state): string => $state->label())
                    ->color(fn (ResearchStatus $state): string => $state->color())
                    ->sortable(),
                TextColumn::make('researched_by')
                    ->badge()
                    ->formatStateUsing(fn (ResearchedBy $state): string => $state->label())
                    ->toggleable(),
                IconColumn::make('raw_markdown')
                    ->label('Brief')
                    ->state(fn (Research $record): bool => filled($record->raw_markdown))
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('last_verified')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(EnumOptions::map(ResearchStatus::cases())),
                SelectFilter::make('researched_by')
                    ->options(EnumOptions::map(ResearchedBy::cases())),
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
