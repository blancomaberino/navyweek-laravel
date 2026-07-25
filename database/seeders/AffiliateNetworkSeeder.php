<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Catalog\Models\AffiliateNetwork;
use Illuminate\Database\Seeder;

/**
 * Seeds the affiliate-network registry from the legacy `NETWORK_SUBID_REGISTRY`
 * (networks.ts). Idempotent (upsert on `key`). Param keys are settled conventions
 * ported verbatim; re-verify against each network's live spec before it goes live.
 */
class AffiliateNetworkSeeder extends Seeder
{
    public function run(): void
    {
        $networks = [
            ['key' => 'direct', 'name' => 'Direct (UTM fallback)', 'subid_param' => 'utm_content', 'extra_params' => ['utm_source' => 'navyweek', 'utm_medium' => 'referral']],
            ['key' => 'impact', 'name' => 'Impact', 'subid_param' => 'subId1', 'extra_params' => null],
            ['key' => 'cj', 'name' => 'CJ Affiliate', 'subid_param' => 'sid', 'extra_params' => null],
            ['key' => 'awin', 'name' => 'Awin', 'subid_param' => 'clickref', 'extra_params' => null],
            ['key' => 'rakuten', 'name' => 'Rakuten Advertising', 'subid_param' => 'u1', 'extra_params' => null],
            ['key' => 'avantlink', 'name' => 'AvantLink', 'subid_param' => 'ctc', 'extra_params' => null],
            ['key' => 'amazon', 'name' => 'Amazon Associates', 'subid_param' => 'ascsubtag', 'extra_params' => null],
        ];

        foreach ($networks as $network) {
            AffiliateNetwork::query()->updateOrCreate(
                ['key' => $network['key']],
                $network,
            );
        }
    }
}
