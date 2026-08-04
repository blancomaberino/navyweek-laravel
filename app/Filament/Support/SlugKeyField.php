<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Shared helpers for Filament "stable kebab-case `key`" inputs. Keeps the
 * "store the slug canonically + check uniqueness against that same slug" rule in
 * one place so every resource with a slug identity (Skill.key, Menu.key)
 * normalizes and validates identically instead of hand-copying the closures
 * (mirrors {@see PathField} for routable paths).
 */
final class SlugKeyField
{
    /**
     * A `dehydrateStateUsing` closure that persists the canonical kebab-case slug,
     * so a case/whitespace variant can't slip past the uniqueness check below.
     * Empty/non-string state persists as '' so the field's `required` rule reports
     * the empty case.
     */
    public static function canonicalDehydrator(): Closure
    {
        return static fn (mixed $state): string => is_string($state) ? Str::slug($state) : '';
    }

    /**
     * A uniqueness `->rule(...)` that checks the slugged value against the model's
     * `key` column (ignoring the edited record), failing with `$message`. Checking
     * the slugged form — not the raw input — is why `->unique()` can't be used.
     *
     * @param  class-string<Model>  $model
     */
    public static function uniqueRule(string $model, string $message): Closure
    {
        return static function (?Model $record) use ($model, $message): Closure {
            return static function (string $attribute, mixed $value, Closure $fail) use ($model, $record, $message): void {
                if (! is_string($value) || trim($value) === '') {
                    return; // `required` reports the empty case
                }
                $query = $model::query();
                if (($ignoreKey = $record?->getKey()) !== null) {
                    $query->whereKeyNot($ignoreKey);
                }
                // Drop to the base query builder for the string-column filter — `key`
                // is a valid column on every model this helper serves, but a generic
                // Builder<Model> can't prove that to static analysis.
                if ($query->getQuery()->where('key', Str::slug($value))->exists()) {
                    $fail($message);
                }
            };
        };
    }
}
