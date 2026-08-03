<?php

declare(strict_types=1);

namespace App\Filament\Resources\Authors\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The editorial team — one row per author byline. Surfaces identity, how many pages
 * cite the author (byline) and how many they reviewed, and whether the account can
 * reach the admin panel.
 */
class AuthorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->color('gray'),
                TextColumn::make('job_title')
                    ->limit(30)
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('authored_pages_count')
                    ->counts('authoredPages')
                    ->label('Pages')
                    ->sortable(),
                TextColumn::make('reviewed_pages_count')
                    ->counts('reviewedPages')
                    ->label('Reviews')
                    ->sortable(),
                IconColumn::make('is_admin')
                    ->label('Panel access')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No bulk delete: the editorial team is a small curated set and deleting an
        // author nulls the byline on every page that cites them (pages.author_id /
        // reviewer_id are nullOnDelete). Deletion is per-author via the Edit page.
    }
}
