<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;
use Stringable;

/**
 * A canonical site path: always a single leading slash and a single trailing
 * slash, lowercased, with collapsed internal slashes. This is the one place the
 * `trailingSlash: 'always'` invariant from the Astro site is enforced, so every
 * route key, redirect, and sitemap entry normalizes identically.
 *
 * The bare root "/" is the sole legitimate path without a "…/" segment pair.
 */
final readonly class UrlPath implements Stringable
{
    private string $value;

    public function __construct(string $raw)
    {
        $trimmed = trim($raw);

        if ($trimmed === '') {
            throw new InvalidArgumentException('A URL path cannot be empty.');
        }

        // Strip any scheme/host if a full URL was passed in.
        if (str_contains($trimmed, '://')) {
            $parsed = parse_url($trimmed, PHP_URL_PATH);
            $trimmed = is_string($parsed) && $parsed !== '' ? $parsed : '/';
        }

        $lowered = strtolower($trimmed);
        $core = trim($lowered, '/');

        // Root, or a string that was all slashes, normalizes to "/".
        $normalized = $core === '' ? '/' : '/'.$core.'/';
        $this->value = (string) preg_replace('#/+#', '/', $normalized);
    }

    public static function from(string $raw): self
    {
        return new self($raw);
    }

    public static function root(): self
    {
        return new self('/');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isRoot(): bool
    {
        return $this->value === '/';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
