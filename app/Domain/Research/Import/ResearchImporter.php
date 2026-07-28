<?php

declare(strict_types=1);

namespace App\Domain\Research\Import;

use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Models\Research;
use App\Domain\Shared\Import\Row;
use App\Domain\Shared\Import\SeedArtifact;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the research briefs. The 1k-brief corpus (~20 MB) is NOT
 * inlined into a JSON artifact; the manifest carries the connection link +
 * provenance and the verbatim Markdown is read from the committed seed-data corpus
 * (database/seed-data/research-briefs/<slug>.md, copied there at export time).
 * Idempotent upsert on (connection_id, version), one transaction.
 *
 * Only `raw_markdown` + `brief_path` + provenance (last_verified, researched_by,
 * skill, status, version) are populated; the structured columns
 * (executive_summary, verified_facts, decision_table, maintenance,
 * recommended_copy, confidence_overall) are left null — parsing the two
 * coexisting brief formats is a deliberate follow-up (mis-celling a YMYL fact
 * from Markdown is exactly what the accuracy-over-volume rule forbids).
 */
final class ResearchImporter
{
    /**
     * @return array<string, int> counts
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            /** @var array<string, int> $connectionIdBySlug */
            $connectionIdBySlug = Connection::query()->pluck('id', 'slug')->all();

            $rows = SeedArtifact::read('research');
            $imported = 0;
            $withMarkdown = 0;

            foreach ($rows as $row) {
                $slug = Row::str($row, 'connection_slug');
                $connectionId = $connectionIdBySlug[$slug] ?? null;
                if ($connectionId === null) {
                    continue;
                }

                $rawMarkdown = SeedArtifact::readText('research-briefs', $slug);
                if ($rawMarkdown !== null) {
                    $withMarkdown++;
                }

                Research::query()->updateOrCreate(
                    ['connection_id' => $connectionId, 'version' => $row['version'] ?? 1],
                    [
                        'brief_path' => $row['brief_path'] ?? null,
                        'raw_markdown' => $rawMarkdown,
                        'last_verified' => $row['last_verified'] ?? null,
                        'researched_by' => $row['researched_by'] ?? 'claude-pipeline',
                        'skill_key' => 'military-discount-research',
                        'skill_version' => '1',
                        'status' => $row['status'] ?? 'draft',
                        'offer_id' => null,
                    ],
                );
                $imported++;
            }

            return [
                'research' => $imported,
                'research_with_markdown' => $withMarkdown,
            ];
        });
    }
}
