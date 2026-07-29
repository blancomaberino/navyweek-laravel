<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\RelationManagers;

use App\Domain\Catalog\Enums\RedemptionChannel;
use App\Filament\Support\EnumOptions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * The offer's redemption steps — the online and in-store "how to redeem" lists
 * merged into one table and discriminated by `channel`, ordered by `sort_order`.
 */
class RedemptionStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'redemptionSteps';

    protected static ?string $title = 'Redemption steps';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('channel')
                ->options(EnumOptions::map(RedemptionChannel::cases()))
                ->required(),
            TextInput::make('title')->required()->maxLength(255),
            Textarea::make('detail')->rows(2)->columnSpanFull(),
            TextInput::make('sort_order')->numeric()->default(0),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (RedemptionChannel $state): string => $state->label()),
                TextColumn::make('title'),
                TextColumn::make('detail')->limit(50)->toggleable(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options(EnumOptions::map(RedemptionChannel::cases())),
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
