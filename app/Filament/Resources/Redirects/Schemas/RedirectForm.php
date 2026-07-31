<?php

declare(strict_types=1);

namespace App\Filament\Resources\Redirects\Schemas;

use App\Domain\Publishing\Models\Redirect;
use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Shared\ValueObjects\UrlPath;
use App\Filament\Support\EnumOptions;
use App\Filament\Support\PathField;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

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
                            // Persist the normalized canonical path (leading + trailing
                            // slash, lowercased, collapsed slashes) so what's stored is
                            // exactly what the middleware matches against.
                            ->dehydrateStateUsing(PathField::canonicalDehydrator())
                            // Reject a normalized self-redirect and a normalized-duplicate
                            // source (per match_type) with a friendly message, rather than
                            // letting a malformed/looping rule go live or hit the DB unique.
                            ->rule(static function (callable $get, ?Model $record): Closure {
                                return static function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    if (! is_string($value) || trim($value) === '') {
                                        return; // `required` already reports the empty case
                                    }
                                    $from = UrlPath::from($value)->value();

                                    if ($from === self::normalizedTarget($get('to_path'))) {
                                        $fail('The source and destination resolve to the same path — a redirect cannot point to itself.');

                                        return;
                                    }

                                    $matchType = $get('match_type');
                                    $ignoreKey = $record?->getKey();
                                    $duplicate = Redirect::query()
                                        ->where('from_path', $from)
                                        ->where('match_type', is_string($matchType) ? $matchType : null)
                                        ->when($ignoreKey !== null, fn ($query) => $query->whereKeyNot($ignoreKey))
                                        ->exists();

                                    if ($duplicate) {
                                        $fail('A redirect with this source path and match type already exists.');
                                    }
                                };
                            })
                            ->helperText('Incoming path, leading + trailing slash (e.g. /discount/old-brand/).')
                            ->columnSpanFull(),
                        TextInput::make('to_path')
                            ->required()
                            ->maxLength(2048)
                            // A relative destination is normalized like a path; an absolute
                            // URL (has a scheme) is kept verbatim.
                            ->dehydrateStateUsing(static fn (mixed $state): string => self::normalizedTarget($state))
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

    /**
     * Normalize a redirect target: an absolute URL (has a scheme) is kept verbatim; a
     * relative destination is normalized to the canonical site-path form. Empty/non-string
     * input yields '' so the caller's `required` rule reports it.
     */
    private static function normalizedTarget(mixed $state): string
    {
        if (! is_string($state) || trim($state) === '') {
            return '';
        }

        return str_contains($state, '://') ? $state : UrlPath::from($state)->value();
    }
}
