<?php

declare(strict_types=1);

namespace App\Domain\Navigation\Support;

use Illuminate\Support\Str;

/**
 * The URL-safety policy for editable navigation links, shared by the Filament
 * write-side validation (`MenuItemsRelationManager`) and the render-side guard
 * ({@see NavigationTree}) so the two can never drift.
 *
 * A nav link is either a root-relative path / in-page fragment (no scheme, always
 * safe) or an absolute URL using an allowed scheme. Everything else — notably
 * `javascript:` and `data:` — is rejected on write and neutralized on render, so a
 * stored value can never become an executable `href`.
 */
final class LinkUrl
{
    /** @var list<string> */
    public const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public static function isAllowed(string $url): bool
    {
        $trimmed = ltrim($url);

        // Root-relative path, in-page fragment, or scheme-relative `//host`.
        if ($trimmed === ''
            || str_starts_with($trimmed, '/')
            || str_starts_with($trimmed, '#')) {
            return true;
        }

        $scheme = Str::lower((string) parse_url($trimmed, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    /**
     * The URL if it passes {@see isAllowed()}, otherwise a safe `#` placeholder.
     */
    /**
     * Whether the value is an absolute http(s) URL — the bar for an EXTERNAL
     * IDENTITY (schema.org `sameAs`, `url`), which is stricter than the href bar.
     *
     * {@see isAllowed()} deliberately passes '', '/path' and '#frag' because those
     * are legitimate hrefs. None of them is an identity: publishing `sameAs: [""]`
     * or a relative path is invalid structured data, so callers that feed a schema
     * graph must use THIS, not `isAllowed()`.
     */
    public static function isAbsoluteHttpUrl(?string $url): bool
    {
        $trimmed = ltrim((string) $url);

        if ($trimmed === '') {
            return false;
        }

        $scheme = parse_url($trimmed, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(mb_strtolower($scheme), ['http', 'https'], true);
    }

    public static function sanitize(string $url): string
    {
        return self::isAllowed($url) ? $url : '#';
    }
}
