<?php

declare(strict_types=1);

namespace App\Domain\Crm\Import;

use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Models\ConnectionAlias;
use App\Domain\Shared\Import\Row;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the CRM `connections` (the ~15.3k-brand universe seeded
 * from the reconciled queue, overlaid with the 981 published brands' editorial
 * fields) + `connection_aliases`. Idempotent, one transaction.
 *
 * Two resolution passes are needed because both `duplicate_of` (self-referential
 * FK) and an alias's `connection_id` point at another connection by slug, which
 * only exists after every row is inserted:
 *   1. upsert every connection by `slug`; collect the duplicate-of slug pairs.
 *   2. resolve `duplicate_of` slug → id, then upsert the aliases (canonical slug → id).
 *
 * The per-brand default affiliate network is unset in the legacy data (every
 * brand's monetized link falls back to the `direct` UTM network at the link
 * level), so `default_affiliate_network_id` is left null here.
 */
final class ConnectionImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            $rows = SeedArtifact::read('connections');

            /** @var array<string, string> $duplicatePairs slug → canonical slug */
            $duplicatePairs = [];

            foreach ($rows as $row) {
                $dupSlug = $row['duplicate_of_slug'] ?? null;
                unset($row['duplicate_of_slug']);

                if (is_string($dupSlug) && $dupSlug !== '') {
                    $duplicatePairs[Row::str($row, 'slug')] = $dupSlug;
                }

                Connection::query()->updateOrCreate(['slug' => $row['slug']], $row);
            }

            // Pass 2 — resolve self-referential duplicate_of by slug.
            /** @var array<string, int> $idBySlug */
            $idBySlug = Connection::query()->pluck('id', 'slug')->all();
            foreach ($duplicatePairs as $slug => $canonicalSlug) {
                $canonicalId = $idBySlug[$canonicalSlug] ?? null;
                if ($canonicalId !== null) {
                    Connection::query()->where('slug', $slug)->update(['duplicate_of' => $canonicalId]);
                }
            }

            return [
                'connections' => count($rows),
                'connection_aliases' => $this->importAliases($idBySlug),
            ];
        });
    }

    /**
     * @param  array<string, int>  $idBySlug
     */
    private function importAliases(array $idBySlug): int
    {
        $rows = SeedArtifact::read('connection-aliases');
        $imported = 0;

        foreach ($rows as $row) {
            $canonicalId = $idBySlug[Row::str($row, 'canonical_slug')] ?? null;
            if ($canonicalId === null) {
                continue; // alias points at a brand not in the universe — skip (logged by count)
            }

            ConnectionAlias::query()->updateOrCreate(
                ['alias_slug' => $row['alias_slug']],
                ['connection_id' => $canonicalId],
            );
            $imported++;
        }

        return $imported;
    }
}
