<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects\Schemas;

use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Publishing\Models\Redirect;
use App\Filament\Support\EnumOptions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

/**
 * Edit form for a `redirects` row. `hits` is a read-only counter maintained by the
 * middleware, so it is not editable here.
 */
class RedirectForm
{
    /**
     * The HTTP status codes a redirect rule may use.
     *
     * @var array<int, string>
     */
    private const STATUSES = [
        301 => '301 — Moved Permanently',
        302 => '302 — Found (temporary)',
        307 => '307 — Temporary Redirect',
        308 => '308 — Permanent Redirect',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('from_path')
                            ->required()
                            ->maxLength(2048)
                            // Unique per (from_path, match_type): the same path may carry
                            // both an exact rule and a prefix (descendants) rule.
                            ->unique(Redirect::class, 'from_path', ignoreRecord: true, modifyRuleUsing: function (Unique $rule, callable $get): Unique {
                                $matchType = $get('match_type');

                                return $rule->where('match_type', is_string($matchType) ? $matchType : null);
                            })
                            ->helperText('Incoming path, leading + trailing slash (e.g. /discount/old-brand/).')
                            ->columnSpanFull(),
                        TextInput::make('to_path')
                            ->required()
                            ->maxLength(2048)
                            ->helperText('Destination path or absolute URL.')
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(self::STATUSES)
                            ->default(301)
                            ->required(),
                        Select::make('match_type')
                            ->options(EnumOptions::map(RedirectMatchType::cases()))
                            ->default(RedirectMatchType::Exact->value)
                            ->required()
                            ->helperText('Exact matches one path; prefix matches all descendants.'),
                        Select::make('reason')
                            ->options(Redirect::REASONS)
                            ->default('manual')
                            ->required(),
                        Toggle::make('is_active')
                            ->default(true)
                            ->helperText('Inactive rules are ignored by the middleware.'),
                    ]),
            ]);
    }
}
