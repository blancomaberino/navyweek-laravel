<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use Illuminate\Support\Facades\Config;

/**
 * Absolute-URL helpers shared by the SEO serializers. The canonical origin is the
 * https www host from `config('site.url')`; `withTrailingSlash` mirrors the legacy
 * `src/lib/seo.ts` guard (a single trailing slash, existing slash / file extension
 * left alone) so every canonical, JSON-LD `@id`, and breadcrumb URL is built the
 * same way.
 */
final class SeoUrl
{
    /** The canonical site origin without a trailing slash (e.g. https://www.navyweek.org). */
    public static function site(): string
    {
        return rtrim(Config::string('site.url'), '/');
    }

    /** Absolute URL for a site path, forcing a single trailing slash. */
    public static function absolute(string $path): string
    {
        return self::site().self::withTrailingSlash($path);
    }

    public static function withTrailingSlash(string $path): string
    {
        if ($path === '' || $path === '/') {
            return '/';
        }

        return str_ends_with($path, '/') ? $path : $path.'/';
    }
}
