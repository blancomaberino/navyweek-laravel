<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects\Tables;

use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Publishing\Models\Redirect;
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
 * The redirect store — one row per 301 rule. Surfaces source/target, status, the
 * rule provenance, match type, and the live hit counter; filters by match type,
 * provenance, and active state.
 */
class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('to_path')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('reason')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('match_type')
                    ->badge()
                    ->formatStateUsing(fn (RedirectMatchType $state): string => $state->label())
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('hits')
                    ->numeric()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('match_type')
                    ->options(EnumOptions::map(RedirectMatchType::cases())),
                SelectFilter::make('reason')
                    ->options(Redirect::REASONS),
                TernaryFilter::make('is_active')->label('Active'),
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
