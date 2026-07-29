<?php

declare(strict_types=1);

namespace App\Filament\Resources\Skills\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The skill registry — one row per research/QA skill. Surfaces the key, name,
 * current version, a short content-hash, and how many briefs cite the skill.
 */
class SkillsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('key')
            ->columns([
                TextColumn::make('key')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('current_version')
                    ->badge()
                    ->sortable(),
                TextColumn::make('content_hash')
                    ->label('Hash')
                    ->limit(12)
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('research_count')
                    ->counts('research')
                    ->label('Briefs')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
