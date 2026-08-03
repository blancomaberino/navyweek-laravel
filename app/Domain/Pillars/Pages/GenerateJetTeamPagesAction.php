<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Pages;

use App\Domain\Pillars\Repositories\JetTeamRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

/**
 * Generates the jet-team `pages`: one hub per team (`/{team}/`, pageable → JetTeam)
 * plus one page per published city guide (`/{team}/{slug}/`, pageable → JetTeamCity).
 * City guides are published-only (the hub schedule still lists every stop). Idempotent;
 * build-clock + default byline live in the repository.
 */
final class GenerateJetTeamPagesAction
{
    public function __construct(
        private readonly JetTeamRepositoryInterface $jetTeams,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of jet-team pages generated (hubs + published cities)
     */
    public function __invoke(): int
    {
        $count = 0;

        foreach ($this->jetTeams->allTeams() as $team) {
            $this->pages->upsertPillarPage("{$team->base_path}/", [
                'page_type' => PageType::JetTeam,
                'slug' => trim($team->base_path, '/'),
                'title' => $team->meta_title,
                'meta_description' => $team->meta_description,
                'og_image_path' => $team->og_image,
                'date_published' => $team->date_published,
                'date_modified' => $team->date_modified,
                'is_published' => true,
            ], $team);
            $count++;

            foreach ($this->jetTeams->publishedCities($team->team) as $city) {
                $this->pages->upsertPillarPage("{$team->base_path}/{$city->slug}/", [
                    'page_type' => PageType::JetTeamCity,
                    'slug' => $city->slug,
                    'title' => $city->meta_title,
                    'meta_description' => $city->meta_description,
                    'og_type' => 'article',
                    'og_image_path' => $city->og_image,
                    'date_published' => $city->date_published,
                    'date_modified' => $city->date_modified,
                    'is_published' => true,
                ], $city);
                $count++;
            }
        }

        return $count;
    }
}
