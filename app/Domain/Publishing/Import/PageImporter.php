<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Import;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Models\Page;
use App\Domain\Shared\Import\Row;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the discount-brand `pages`. Each row points its polymorphic
 * `pageable` at the brand's primary Offer (resolved connection slug → connection
 * → primary offer). Idempotent upsert on the unique `url_path`, one transaction.
 * Offers must already be imported.
 */
final class PageImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            /** @var array<string, int> $connectionIdBySlug */
            $connectionIdBySlug = Connection::query()->pluck('id', 'slug')->all();
            /** @var array<int, int> $primaryOfferByConnectionId */
            $primaryOfferByConnectionId = Offer::query()
                ->where('is_primary', true)
                ->pluck('id', 'connection_id')
                ->all();

            $rows = SeedArtifact::read('pages');

            foreach ($rows as $row) {
                $slug = Row::str($row, 'connection_slug');
                unset($row['connection_slug']);

                $connectionId = $connectionIdBySlug[$slug] ?? null;
                $offerId = $connectionId !== null ? ($primaryOfferByConnectionId[$connectionId] ?? null) : null;

                // Point the polymorphic `pageable` at the brand's primary offer (or
                // clear it) and upsert the page in a single write, keyed on url_path.
                $page = Page::query()->firstOrNew(['url_path' => $row['url_path']]);
                $page->fill($row);
                if ($offerId !== null) {
                    $offer = new Offer;
                    $offer->id = $offerId;
                    $page->pageable()->associate($offer);
                } else {
                    $page->pageable()->dissociate();
                }
                $page->save();
            }

            return ['pages' => count($rows)];
        });
    }
}
