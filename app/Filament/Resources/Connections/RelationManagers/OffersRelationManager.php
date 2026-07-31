<?php

declare(strict_types=1);

namespace App\Filament\Resources\Connections\RelationManagers;

use App\Domain\Catalog\Enums\OfferType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The brand's offers, shown read-only on the Connection page. Editing lives in the
 * dedicated OfferResource; this is a quick at-a-glance list on the CRM record.
 */
class OffersRelationManager extends RelationManager
{
    protected static string $relationship = 'offers';

    protected static ?string $title = 'Offers';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('offer_type')
                    ->badge()
                    ->formatStateUsing(fn (OfferType $state): string => $state->label()),
                TextColumn::make('headline_discount')
                    ->limit(40),
                IconColumn::make('is_primary')
                    ->boolean()
                    ->label('Primary'),
                IconColumn::make('is_published')
                    ->boolean()
                    ->label('Published'),
            ]);
    }
}
