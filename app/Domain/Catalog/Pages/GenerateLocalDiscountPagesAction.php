<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Pages;

use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Repositories\LocalDiscountRepositoryInterface;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Derives the `pages` routing/SEO rows for the whole local-discount family: the detail
 * pages (`/discounts/{state}/{city}/{business}/`, `pageable` → the LocalDiscount) and the
 * three rollup **hub** levels — the `/discounts/` root, per-state (`/discounts/{state}/`),
 * and per-city (`/discounts/{state}/{city}/`) index pages (null pageable; the render reads
 * the rollup at request time). Idempotent: upserts by url_path, so the build clock (in the
 * repository) preserves each page's original `date_published`. Hub pages carry no source
 * record, so their title/meta are synthesized and their dates take the freshest child date.
 */
final class GenerateLocalDiscountPagesAction
{
    public function __construct(
        private readonly LocalDiscountRepositoryInterface $locals,
        private readonly PageRepositoryInterface $pages,
    ) {}

    /**
     * @return int the number of local-discount pages (detail + hubs) generated/refreshed
     */
    public function __invoke(): int
    {
        $locals = $this->locals->all();
        $count = 0;

        foreach ($locals as $local) {
            $this->pages->upsertPillarPage(
                "local-discount:{$local->state}:{$local->city}:{$local->business_slug}",
                PagePaths::child('local_discounts', $local->state, $local->city, $local->business_slug),
                [
                    'page_type' => PageType::LocalDiscount,
                    'slug' => $local->business_slug,
                    'title' => $local->meta_title,
                    'meta_description' => $local->meta_description,
                    'og_image_path' => $local->og_image,
                    'date_published' => $local->date_published,
                    'date_modified' => $local->date_modified,
                    'is_published' => true,
                ],
                $local,
            );
            $count++;
        }

        return $count + $this->generateHubs($locals);
    }

    /**
     * The root, per-state, and per-city rollup hubs (null pageable). Dates take the
     * freshest child `date_modified` so the hub's build-clock reflects its newest entry.
     *
     * @param  Collection<int, LocalDiscount>  $locals
     */
    private function generateHubs(Collection $locals): int
    {
        if ($locals->isEmpty()) {
            return 0;
        }

        $count = 0;

        // Root hub.
        $this->hub('local-hub:root', PagePaths::root('local_discounts'), 'discounts',
            'Local Military & Veteran Discounts by State | NavyWeek.org',
            'Browse verified local-business military and veteran discounts by state and city.',
            $this->freshest($locals));
        $count++;

        foreach ($locals->groupBy('state') as $inState) {
            /** @var LocalDiscount $first */
            $first = $inState->first();
            $this->hub("local-hub:state:{$first->state}", PagePaths::child('local_discounts', $first->state), $first->state,
                "Military & Veteran Discounts in {$first->state_name} | NavyWeek.org",
                "Verified local-business military and veteran discounts across {$first->state_name}.",
                $this->freshest($inState));
            $count++;

            foreach ($inState->groupBy('city') as $inCity) {
                /** @var LocalDiscount $c */
                $c = $inCity->first();
                $this->hub("local-hub:city:{$c->state}:{$c->city}", PagePaths::child('local_discounts', $c->state, $c->city), $c->city,
                    "Military & Veteran Discounts in {$c->city_name}, {$c->state_abbr} | NavyWeek.org",
                    "Verified local-business military and veteran discounts in {$c->city_name}, {$c->state_name}.",
                    $this->freshest($inCity));
                $count++;
            }
        }

        return $count;
    }

    private function hub(string $generationKey, string $urlPath, string $slug, string $title, string $meta, Carbon $date): void
    {
        $this->pages->upsertPillarPage($generationKey, $urlPath, [
            'page_type' => PageType::LocalDiscount,
            'slug' => $slug,
            'title' => $title,
            'meta_description' => $meta,
            'og_image_path' => null,
            'date_published' => $date,
            'date_modified' => $date,
            'is_published' => true,
        ]);
    }

    /**
     * The freshest `date_modified` among a set of local discounts.
     *
     * @param  Collection<int, LocalDiscount>  $locals
     */
    private function freshest(Collection $locals): Carbon
    {
        $max = $locals->max(fn (LocalDiscount $l): Carbon => $l->date_modified);

        return $max instanceof Carbon ? $max : Carbon::now();
    }
}
