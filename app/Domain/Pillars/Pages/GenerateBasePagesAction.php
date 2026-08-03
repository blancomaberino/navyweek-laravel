<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Repositories\BaseRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;

/**
 * Derives the `pages` routing/SEO rows for every naval base from the base records
 * themselves (each base carries its own meta_title/meta_description). One published
 * `pages` row per base under `config('publishing.paths.bases')` (default `/navy-bases/`),
 * `pageable` → the Base. Idempotent: re-running upserts by the stable `generation_key`
 * ("base:{slug}") and preserves each page's original `date_published` and any editor
 * rename (the build clock + path rules live in the repository). The base's `last_updated`
 * seeds the
 * build-clock dates on first generation, matching the legacy Article dates.
 */
final class GenerateBasePagesAction
{
    public function __construct(
        private readonly BaseRepositoryInterface $bases,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of base pages generated/refreshed
     */
    public function __invoke(): int
    {
        $count = 0;

        foreach ($this->bases->all() as $base) {
            $this->pages->upsertPillarPage(
                "base:{$base->slug}",
                PagePaths::child('bases', $base->slug),
                [
                    'page_type' => PageType::Base,
                    'slug' => $base->slug,
                    'title' => $base->meta_title,
                    'meta_description' => $base->meta_description,
                    'og_image_path' => "/og/bases/{$base->slug}.png",
                    'date_published' => $base->last_updated,
                    'date_modified' => $base->last_updated,
                    'is_published' => true,
                ],
                $base,
            );
            $count++;
        }

        return $count;
    }
}
