<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Facades\Config;

/**
 * The per-page `<head>` SEO block — title, description, robots, canonical, the two
 * alternate feeds, Open Graph / Twitter, and JSON-LD. Ported 1:1 from
 * `src/lib/seo.ts` (`buildSEOData` + `renderSEOToHTML`): the tag order, the
 * `&<>"'` escaping, the `<` → `<` JSON-LD guard, and the Organization
 * auto-prepend (unless noindex) all match the legacy serializer so the emitted
 * bytes stay identical. Rendered via `{!! $seoHead !!}` in the base layout.
 */
final class SeoHead
{
    /**
     * @param  list<array<string, mixed>>  $schemas
     */
    private function __construct(
        private readonly string $title,
        private readonly string $description,
        private readonly string $canonicalUrl,
        private readonly string $ogType,
        private readonly string $ogImage,
        private readonly string $imageAlt,
        private readonly bool $noindex,
        private readonly array $schemas,
    ) {}

    /**
     * @param  list<array<string, mixed>>|null  $schemas  Computed JSON-LD for
     *                                                    page types that build it
     *                                                    at render; falls back to
     *                                                    the page's stored json_ld.
     */
    public static function forPage(Page $page, ?array $schemas = null): self
    {
        $siteUrl = SeoUrl::site();
        $title = (string) $page->title;
        $noindex = (bool) $page->noindex;

        $canonicalPath = $page->canonical_path ?? $page->url_path;
        $canonicalUrl = SeoUrl::absolute((string) $canonicalPath);

        $ogImage = $page->og_image_path !== null && $page->og_image_path !== ''
            ? $siteUrl.$page->og_image_path
            : $siteUrl.Config::string('site.default_og_image');

        $userSchemas = $schemas ?? self::normalizeSchemas($page->json_ld);
        // Organization is prepended on indexable pages only (matches buildSEOData).
        $schemas = $noindex ? $userSchemas : array_merge([OrganizationSchema::build()], $userSchemas);

        return new self(
            title: $title,
            description: (string) $page->meta_description,
            canonicalUrl: $canonicalUrl,
            ogType: $page->og_type ?? 'website',
            ogImage: $ogImage,
            imageAlt: "{$title} — NavyWeek.org Open Graph image",
            noindex: $noindex,
            schemas: $schemas,
        );
    }

    public function isNoindex(): bool
    {
        return $this->noindex;
    }

    /**
     * Serialize the head block. Parts are joined by newline + 4 spaces to match the
     * legacy `parts.join('\n    ')` indentation at the injection point.
     */
    public function render(): string
    {
        $siteUrl = SeoUrl::site();
        $siteName = Config::string('site.name');

        $parts = [];
        $parts[] = '<title>'.self::escape($this->title).'</title>';
        $parts[] = '<meta name="description" content="'.self::escape($this->description).'"/>';
        if ($this->noindex) {
            $parts[] = '<meta name="robots" content="noindex, nofollow"/>';
        }
        $parts[] = '<link rel="canonical" href="'.self::escape($this->canonicalUrl).'"/>';
        $parts[] = '<link rel="alternate" type="application/json" href="'.$siteUrl.'/data/navy-week-2026.json" title="Navy Week 2026 JSON feed"/>';
        $parts[] = '<link rel="alternate" type="text/plain" href="'.$siteUrl.'/llms.txt" title="NavyWeek.org llms.txt"/>';
        $parts[] = '<meta property="og:type" content="'.self::escape($this->ogType).'"/>';
        $parts[] = '<meta property="og:site_name" content="'.$siteName.'"/>';
        $parts[] = '<meta property="og:title" content="'.self::escape($this->title).'"/>';
        $parts[] = '<meta property="og:description" content="'.self::escape($this->description).'"/>';
        $parts[] = '<meta property="og:image" content="'.self::escape($this->ogImage).'"/>';
        $parts[] = '<meta property="og:image:width" content="1200"/>';
        $parts[] = '<meta property="og:image:height" content="630"/>';
        $parts[] = '<meta property="og:image:alt" content="'.self::escape($this->imageAlt).'"/>';
        $parts[] = '<meta property="og:url" content="'.self::escape($this->canonicalUrl).'"/>';
        $parts[] = '<meta property="og:locale" content="en_US"/>';
        $parts[] = '<meta name="twitter:card" content="summary_large_image"/>';
        $parts[] = '<meta name="twitter:title" content="'.self::escape($this->title).'"/>';
        $parts[] = '<meta name="twitter:description" content="'.self::escape($this->description).'"/>';
        $parts[] = '<meta name="twitter:image" content="'.self::escape($this->ogImage).'"/>';
        $parts[] = '<meta name="twitter:image:alt" content="'.self::escape($this->imageAlt).'"/>';

        foreach ($this->schemas as $schema) {
            $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            // Match the legacy `.replace(/</g, '<')` so a "<" inside JSON-LD
            // can never break out of the <script> block. Single-quoted → literal.
            $json = str_replace('<', '\\u003c', (string) $json);
            $parts[] = '<script type="application/ld+json" data-seo="1">'.$json.'</script>';
        }

        return implode("\n    ", $parts);
    }

    /**
     * `&<>"'` escaping, matching the legacy `escapeHtml` (note `'` → `&#x27;`, not
     * PHP's default `&#039;`). `&` is replaced first so the later entities aren't
     * double-escaped.
     */
    private static function escape(string $s): string
    {
        return str_replace(
            ['&', '<', '>', '"', "'"],
            ['&amp;', '&lt;', '&gt;', '&quot;', '&#x27;'],
            $s,
        );
    }

    /**
     * Normalize the page's stored JSON-LD (a single object or a list) into a list
     * of schema objects, matching buildSEOData's `Array.isArray ? jsonLd : [jsonLd]`.
     *
     * @param  array<mixed>|null  $jsonLd
     * @return list<array<string, mixed>>
     */
    private static function normalizeSchemas(?array $jsonLd): array
    {
        if ($jsonLd === null || $jsonLd === []) {
            return [];
        }

        // A list of schema objects passes through; a single object is wrapped.
        /** @var list<array<string, mixed>> $schemas */
        $schemas = array_is_list($jsonLd) ? $jsonLd : [$jsonLd];

        return $schemas;
    }
}
