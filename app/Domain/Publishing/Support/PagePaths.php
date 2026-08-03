<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Support;

use Illuminate\Support\Facades\Config;

/**
 * The single source of truth for generated page URL *prefixes*. Both the page
 * generators (which seed `pages.url_path`) and the SEO schemas (which build
 * breadcrumb-ancestor and hub-link URLs) read family roots from here, so changing
 * `config('publishing.paths.*')` moves an entire family from one place.
 *
 * A page's OWN canonical URL always comes from its stored `pages.url_path` (never
 * rebuilt here) — this helper only supplies the family prefix and paths *derived*
 * from it (a base's country/state breadcrumb, a hub link, a child detail URL).
 */
final class PagePaths
{
    /**
     * The root prefix for a page family, always leading- and trailing-slashed
     * (e.g. `root('bases')` => "/navy-bases/"). Fails loud on an unknown family.
     */
    public static function root(string $family): string
    {
        return '/'.trim(Config::string("publishing.paths.{$family}"), '/').'/';
    }

    /**
     * A path under a family root: root + slug segments, single-trailing-slashed.
     * e.g. `child('bases', 'overseas')` => "/navy-bases/overseas/";
     *      `child('local_discounts', $state, $city)` => "/discounts/{$state}/{$city}/".
     */
    public static function child(string $family, string ...$segments): string
    {
        $suffix = implode('/', array_map(
            static fn (string $segment): string => trim($segment, '/'),
            array_filter($segments, static fn (string $segment): bool => trim($segment, '/') !== ''),
        ));

        return self::root($family).($suffix === '' ? '' : $suffix.'/');
    }
}
