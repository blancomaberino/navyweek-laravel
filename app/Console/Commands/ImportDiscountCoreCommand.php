<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Catalog\Import\OfferImporter;
use App\Domain\Crm\Import\ConnectionImporter;
use App\Domain\Publishing\Import\PageImporter;
use App\Domain\Research\Import\ResearchImporter;
use Database\Seeders\AffiliateNetworkSeeder;
use Database\Seeders\AudienceSeeder;
use Illuminate\Console\Command;

/**
 * Stage-B entrypoint for the discount CORE — the flat legacy `Discount` (+ the
 * brand queue) normalized across connections → offers (+ tiers/steps/audience/
 * faqs/sources/affiliate links) → pages → research. Runs the per-aggregate
 * importers in dependency order inside their own transactions; idempotent.
 *
 * The Audience + AffiliateNetwork reference lookups (seeded from the enum /
 * registry) are ensured first, since the offer/connection FKs resolve against
 * them by key.
 */
final class ImportDiscountCoreCommand extends Command
{
    protected $signature = 'import:discount-core';

    protected $description = 'Import the discount core (connections + offers + pages + research) from database/seed-data.';

    public function handle(
        ConnectionImporter $connections,
        OfferImporter $offers,
        PageImporter $pages,
        ResearchImporter $research,
    ): int {
        // Reference lookups the FKs resolve against (idempotent).
        app(AudienceSeeder::class)->run();
        app(AffiliateNetworkSeeder::class)->run();

        $counts = [
            ...$connections->import(),
            ...$offers->import(),
            ...$pages->import(),
            ...$research->import(),
        ];

        foreach ($counts as $table => $count) {
            $this->line(sprintf('  %-24s %d', $table, $count));
        }

        $this->info('Discount core imported.');

        return self::SUCCESS;
    }
}
