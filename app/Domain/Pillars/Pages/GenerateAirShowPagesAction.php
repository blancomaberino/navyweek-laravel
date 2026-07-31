<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Repositories\AirShowRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

/**
 * Generates the air-show `pages`: one published detail page per published show
 * (`/air-show/{slug}/`, pageable → AirShow) plus the single hub page (`/air-show/`,
 * pageable → AirShowHubMeta). Both share `PageType::AirShow`, distinguished at render
 * by their pageable class. A show with a `canonical_override` still gets a page but
 * canonicalizes elsewhere (its Event node is suppressed by `emitsEventSchema`).
 * Idempotent; build-clock + default byline live in the repository.
 */
final class GenerateAirShowPagesAction
{
    public function __construct(
        private readonly AirShowRepositoryInterface $airShows,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of air-show pages generated (published shows + hub)
     */
    public function __invoke(): int
    {
        $count = 0;

        foreach ($this->airShows->published() as $show) {
            $attributes = [
                'page_type' => PageType::AirShow,
                'slug' => $show->slug,
                'title' => $show->meta_title,
                'meta_description' => $show->meta_description,
                'og_type' => 'article',
                'og_image_path' => $show->og_image,
                'date_published' => $show->date_published,
                'date_modified' => $show->date_modified,
                'is_published' => true,
            ];
            // A disambiguation page canonicalizes to its primary show.
            if ($show->canonical_override !== null) {
                $attributes['canonical_path'] = $show->canonical_override;
            }

            $this->pages->upsertPillarPage("/air-show/{$show->slug}/", $attributes, $show);
            $count++;
        }

        $hub = $this->airShows->hub();
        if ($hub !== null) {
            $this->pages->upsertPillarPage('/air-show/', [
                'page_type' => PageType::AirShow,
                'slug' => 'air-show',
                'title' => $hub->meta_title,
                'meta_description' => $hub->meta_description,
                'og_image_path' => $hub->og_image,
                'date_published' => $hub->date_published,
                'date_modified' => $hub->date_modified,
                'is_published' => true,
            ], $hub);
            $count++;
        }

        return $count;
    }
}
