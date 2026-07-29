<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use Illuminate\Support\Facades\Config;

/**
 * The site-wide Organization JSON-LD, auto-prepended to every indexable page's
 * schema list. Ported 1:1 from `src/lib/seo.ts` `buildOrganizationSchema()` — the
 * key order is preserved so the serialized JSON stays byte-identical to the legacy
 * output.
 */
final class OrganizationSchema
{
    /**
     * @return array<string, string>
     */
    public static function build(): array
    {
        $url = rtrim(Config::string('site.url'), '/');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => "{$url}/#organization",
            'name' => Config::string('site.name'),
            'alternateName' => 'NavyWeek.org',
            'url' => $url,
            'logo' => "{$url}/favicon.svg",
            'description' => 'NavyWeek.org is an independent, unofficial guide to the U.S. Navy Week program. It is not affiliated with the United States Navy or the Navy Office of Community Outreach (NAVCO); it aggregates and explains publicly available information about Navy Week 2026 host cities, schedules, and events.',
        ];
    }
}
