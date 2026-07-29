<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Tables;

use App\Domain\Publishing\Enums\PageType;
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
 * The published-URL registry — one row per `pages.url_path`. Surfaces the page
 * type, publish/noindex flags, and the polymorphic target class; filters by type
 * and the index/publish flags.
 */
class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('url_path')
            ->columns([
                TextColumn::make('url_path')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->toggleable(),
                TextColumn::make('page_type')
                    ->badge()
                    ->formatStateUsing(fn (PageType $state): string => $state->label())
                    ->sortable(),
                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),
                IconColumn::make('noindex')
                    ->boolean()
                    ->label('Noindex'),
                TextColumn::make('pageable_type')
                    ->label('Target')
                    ->formatStateUsing(fn (?string $state): string => $state !== null ? class_basename($state) : '—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('page_type')
                    ->options(EnumOptions::map(PageType::cases())),
                TernaryFilter::make('is_published')->label('Published'),
                TernaryFilter::make('noindex')->label('Noindex'),
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
