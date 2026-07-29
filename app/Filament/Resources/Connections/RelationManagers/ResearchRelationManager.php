<?php

declare(strict_types=1);

namespace App\Filament\Resources\Connections\RelationManagers;

use App\Domain\Research\Enums\ResearchedBy;
use App\Domain\Research\Enums\ResearchStatus;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The brand's versioned research briefs, read-only on the Connection page (editing
 * lives in ResearchResource). Newest version first — the audit trail at a glance.
 */
class ResearchRelationManager extends RelationManager
{
    protected static string $relationship = 'research';

    protected static ?string $title = 'Research';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ResearchStatus $state): string => $state->label()),
                TextColumn::make('researched_by')
                    ->badge()
                    ->formatStateUsing(fn (ResearchedBy $state): string => $state->label()),
                TextColumn::make('last_verified')
                    ->date()
                    ->label('Last verified'),
            ]);
    }
}
