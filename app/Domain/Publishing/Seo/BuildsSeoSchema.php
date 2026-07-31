<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Seo;

use App\Domain\Publishing\Models\Page;
use Illuminate\Support\Facades\Config;

/**
 * Shared JSON-LD building blocks used by more than one page-schema serializer
 * (discount brand guide + category hub). Keeps the date format, og:image fallback,
 * and BreadcrumbList shape in one place so the serializers can't silently diverge.
 */
trait BuildsSeoSchema
{
    /** ISO-8601 date (`Y-m-d`) for a nullable/mixed date attribute, or '' when absent. */
    private static function isoDate(mixed $date): string
    {
        return $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : '';
    }

    /**
     * The absolute og:image URL for a page — the page's own image, falling back to the
     * site default. Mirrors SeoHead's emitted og:image so the JSON-LD image can't
     * diverge from the head tag.
     */
    private static function ogImage(string $site, Page $page): string
    {
        return $page->og_image_path !== null && $page->og_image_path !== ''
            ? $site.$page->og_image_path
            : $site.Config::string('site.default_og_image');
    }

    /**
     * A schema.org BreadcrumbList node from ordered `{name, url}` crumbs. Each crumb
     * URL runs through {@see SeoUrl::absolute} so the trailing slash matches every
     * other canonical/@id URL.
     *
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    private static function breadcrumb(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(
                static fn (array $item, int $i): array => [
                    '@type' => 'ListItem',
                    'position' => $i + 1,
                    'name' => $item['name'],
                    'item' => SeoUrl::absolute($item['url']),
                ],
                $items,
                array_keys($items),
            ),
        ];
    }
}
