<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Shared\ValueObjects\UrlPath;
use Closure;

/**
 * Shared helpers for Filament path inputs. Keeps the "store the canonical URL path"
 * rule in one place so every resource form that edits a routable path (Page url_path,
 * Redirect from_path) normalizes identically instead of hand-copying the closure.
 */
final class PathField
{
    /**
     * A `dehydrateStateUsing` closure that persists the canonical {@see UrlPath} form
     * (leading + trailing slash, lowercased, collapsed slashes) — exactly what the
     * router/middleware matches against. Empty or non-string state persists as ''
     * so the field's own `required` rule reports the empty case.
     */
    public static function canonicalDehydrator(): Closure
    {
        return static fn (mixed $state): string => is_string($state) && trim($state) !== ''
            ? UrlPath::from($state)->value()
            : '';
    }
}
