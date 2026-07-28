<?php

declare(strict_types=1);

namespace App\Domain\Shared\Import;

use RuntimeException;

/**
 * Typed accessors for a decoded seed-artifact row (`array<string, mixed>`). The
 * JSON handoff is untyped, so importers read scalar fields through here to fail
 * loud on a malformed artifact rather than silently coercing `mixed` (which the
 * `(string) $mixed` cast would, and which PHPStan max forbids).
 */
final class Row
{
    /**
     * Read a required string field, throwing if it is absent or not a string.
     *
     * @param  array<string, mixed>  $row
     */
    public static function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (! is_string($value)) {
            throw new RuntimeException("Seed row field \"{$key}\" is missing or not a string.");
        }

        return $value;
    }
}
