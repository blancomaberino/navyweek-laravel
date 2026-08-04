<?php

declare(strict_types=1);

namespace App\Filament\Resources\Menus\Schemas;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use App\Filament\Support\EnumOptions;
use App\Filament\Support\SlugKeyField;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Edit form for a menu. `key` is the stable identity the seeder and render
 * fallback pin to, so it is stored canonical (kebab-case) and uniqueness is
 * checked in that same normalized form (via {@see SlugKeyField}). The header and
 * legal regions are singular — the renderer takes the first menu there — so a
 * second menu in either is rejected rather than silently ignored.
 */
class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('key')
                        ->required()
                        ->maxLength(255)
                        ->dehydrateStateUsing(SlugKeyField::canonicalDehydrator())
                        ->rule(SlugKeyField::uniqueRule(Menu::class, 'A menu with this key already exists.'))
                        ->helperText('Stable kebab-case identifier, e.g. footer-navy-week.'),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Heading shown for footer columns; a label for the header/legal menus.'),
                    Select::make('location')
                        ->options(EnumOptions::map(MenuLocation::cases()))
                        ->required()
                        ->rule(self::singularRegionRule())
                        ->helperText('Where this menu renders. Header and legal allow one menu each; footer holds one per column.'),
                    TextInput::make('sort_order')
                        ->numeric()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Order among menus in the same region (footer columns).'),
                    Toggle::make('is_active')
                        ->default(true)
                        ->helperText('Hidden from the site when off.'),
                ]),
        ]);
    }

    /**
     * Rejects a second menu in the singular header/legal regions — the renderer
     * takes only the first menu there, so a duplicate would never appear.
     */
    private static function singularRegionRule(): Closure
    {
        return static function (?Menu $record): Closure {
            return static function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                $singular = [MenuLocation::Header->value, MenuLocation::Legal->value];
                if (! in_array($value, $singular, true)) {
                    return; // footer holds many menus (one per column)
                }
                $ignoreKey = $record?->getKey();
                $exists = Menu::query()
                    ->where('location', $value)
                    ->when($ignoreKey !== null, fn ($query) => $query->whereKeyNot($ignoreKey))
                    ->exists();
                if ($exists) {
                    $fail('The header and legal regions can each hold only one menu.');
                }
            };
        };
    }
}
