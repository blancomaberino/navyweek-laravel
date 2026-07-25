<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Crm\Enums\Audience as AudienceEnum;
use App\Domain\Crm\Models\Audience;
use Illuminate\Database\Seeder;

/**
 * Seeds the audience lookup from the canonical `Audience` enum vocabulary (the
 * legacy 9 `DiscountAudience` booleans, consolidated to 7 cases — so this seeds 7
 * rows). Idempotent (upsert on `key`); enum declaration order sets `sort_order`.
 */
class AudienceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AudienceEnum::cases() as $order => $case) {
            Audience::query()->updateOrCreate(
                ['key' => $case->value],
                ['label' => $case->label(), 'sort_order' => $order],
            );
        }
    }
}
