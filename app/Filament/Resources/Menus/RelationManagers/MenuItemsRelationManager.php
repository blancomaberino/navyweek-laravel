<?php

declare(strict_types=1);

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Models\MenuItem;
use App\Filament\Support\LinkUrlField;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The links inside a menu — drag-reorderable on `sort_order`. `parent` (optional,
 * one level) nests a link as a dropdown child; `target`/`rel` carry the
 * external-link attributes.
 */
class MenuItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Links';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('label')
                ->required()
                ->maxLength(255),
            TextInput::make('url')
                ->required()
                ->maxLength(2048)
                // Reject a disallowed scheme (e.g. javascript:) on write; the render
                // layer applies the same policy via LinkUrl (defense in depth).
                ->rule(LinkUrlField::rule())
                ->helperText(LinkUrlField::helperText()),
            Select::make('parent_id')
                ->label('Parent (dropdown)')
                ->options(static function (RelationManager $livewire, ?MenuItem $record): array {
                    /** @var Menu $menu */
                    $menu = $livewire->getOwnerRecord();
                    $excludeKey = $record?->getKey();

                    // Only top-level links can be parents (one level of dropdown),
                    // and an item can't be its own parent.
                    return $menu->items()
                        ->whereNull('parent_id')
                        ->when($excludeKey !== null, fn ($query) => $query->whereKeyNot($excludeKey))
                        ->orderBy('sort_order')
                        ->pluck('label', 'id')
                        ->all();
                })
                // A link that already has children can't itself be nested — doing so
                // would push its children to a second level the renderer drops.
                ->disabled(static fn (?MenuItem $record): bool => $record?->children()->exists() ?? false)
                ->searchable()
                ->placeholder('None (top-level)')
                ->helperText('Nest this link under another to make a dropdown (one level).'),
            Select::make('target')
                ->options(['_blank' => 'New tab (_blank)'])
                ->placeholder('Same tab')
                ->helperText('Set _blank for external links.'),
            TextInput::make('rel')
                ->maxLength(255)
                ->helperText('e.g. noopener noreferrer for external links.'),
            Toggle::make('is_active')
                ->default(true)
                ->helperText('Hidden from the site when off.'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('label')
                    ->description(fn (MenuItem $record): ?string => $record->parent_id !== null ? '↳ nested' : null),
                TextColumn::make('url')
                    ->limit(48)
                    ->color('gray'),
                TextColumn::make('parent.label')
                    ->label('Parent')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('target')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
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
