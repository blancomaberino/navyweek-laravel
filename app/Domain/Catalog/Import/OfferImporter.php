<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Import;

use App\Domain\Catalog\Models\AffiliateNetwork;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Audience;
use App\Domain\Crm\Models\Connection;
use App\Domain\Shared\Import\Row;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Stage-B importer for `offers` and everything hanging off one — `offer_tiers`,
 * `redemption_steps`, the `offer_audience` pivot, polymorphic FAQs/sources, and
 * `affiliate_links`. One primary offer per connection (keyed on the connection +
 * offer_type), idempotent in one transaction.
 *
 * The keyless child tables (tiers, steps, affiliate links) are replaced wholesale
 * per offer; the audience pivot is synced to the distinct Audience rows; FAQs and
 * sources use the shared replace helpers. Connections, Audiences, and
 * AffiliateNetworks must already be seeded/imported (the FKs resolve by
 * key/slug).
 */
final class OfferImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            /** @var array<string, int> $connectionIdBySlug */
            $connectionIdBySlug = Connection::query()->pluck('id', 'slug')->all();
            /** @var array<string, int> $audienceIdByKey */
            $audienceIdByKey = Audience::query()->pluck('id', 'key')->all();
            /** @var array<string, int> $networkIdByKey */
            $networkIdByKey = AffiliateNetwork::query()->pluck('id', 'key')->all();

            $rows = SeedArtifact::read('offers');
            $tiers = 0;
            $steps = 0;
            $links = 0;

            foreach ($rows as $row) {
                $slug = Row::str($row, 'connection_slug');
                $connectionId = $connectionIdBySlug[$slug] ?? null;
                if ($connectionId === null) {
                    throw new RuntimeException("offers: no connection for slug \"{$slug}\".");
                }

                /** @var list<array<string, mixed>> $tierRows */
                $tierRows = $row['tiers'] ?? [];
                /** @var list<array<string, mixed>> $onlineSteps */
                $onlineSteps = $row['online_steps'] ?? [];
                /** @var list<array<string, mixed>> $inStoreSteps */
                $inStoreSteps = $row['in_store_steps'] ?? [];
                /** @var list<string> $audienceKeys */
                $audienceKeys = $row['audience_keys'] ?? [];
                /** @var list<array<string, mixed>> $faqs */
                $faqs = $row['faqs'] ?? [];
                /** @var list<array<string, mixed>> $sources */
                $sources = $row['sources'] ?? [];
                /** @var list<array<string, mixed>> $affiliateLinks */
                $affiliateLinks = $row['affiliate_links'] ?? [];
                unset(
                    $row['connection_slug'], $row['tiers'], $row['online_steps'],
                    $row['in_store_steps'], $row['audience_keys'], $row['faqs'],
                    $row['sources'], $row['affiliate_links'],
                );

                // Upsert THROUGH the connection so the `connection_id` FK is set by
                // the relation (one primary offer per connection + offer_type).
                $parent = new Connection;
                $parent->id = $connectionId;
                $offer = $parent->offers()->updateOrCreate(
                    ['offer_type' => $row['offer_type']],
                    $row,
                );

                // Keyless children → wholesale replace per offer.
                $offer->tiers()->delete();
                $offer->tiers()->createMany($tierRows);

                $offer->redemptionSteps()->delete();
                $offer->redemptionSteps()->createMany([...$onlineSteps, ...$inStoreSteps]);

                $offer->replaceFaqs($faqs);
                $offer->replaceSources($sources);

                // Audience pivot → sync to the distinct seeded Audience rows.
                $audienceIds = array_values(array_filter(
                    array_map(static fn (string $k): ?int => $audienceIdByKey[$k] ?? null, $audienceKeys),
                    static fn (?int $id): bool => $id !== null,
                ));
                $offer->audiences()->sync($audienceIds);

                // Affiliate links → wholesale replace per offer.
                $offer->affiliateLinks()->delete();
                foreach ($affiliateLinks as $link) {
                    $networkKey = Row::str($link, 'network_key'); // exporter always emits it ('direct' fallback)
                    $offer->affiliateLinks()->create([
                        'connection_id' => $connectionId,
                        'affiliate_network_id' => $networkIdByKey[$networkKey] ?? null,
                        'base_url' => $link['base_url'] ?? null,
                        'placement' => $link['placement'] ?? null,
                        'rel' => $link['rel'] ?? null,
                    ]);
                    $links++;
                }

                $tiers += count($tierRows);
                $steps += count($onlineSteps) + count($inStoreSteps);
            }

            return [
                'offers' => count($rows),
                'offer_tiers' => $tiers,
                'redemption_steps' => $steps,
                'affiliate_links' => $links,
            ];
        });
    }
}
