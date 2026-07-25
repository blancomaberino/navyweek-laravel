<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Shared\Enums\ConfidenceLevel;
use App\Domain\Shared\Enums\SourceType;
use App\Domain\Shared\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Defaults to an Offer citation; override via ->for($model, 'sourceable').
        return [
            'sourceable_type' => (new Offer)->getMorphClass(),
            'sourceable_id' => Offer::factory(),
            'label' => 'Official military discount page',
            'url' => 'https://www.example.com/military-discount',
            'publisher' => 'Example Brand',
            'source_type' => SourceType::Official,
            'accessed_at' => '2026-07-01',
            'confidence' => ConfidenceLevel::High,
            'sort_order' => 0,
        ];
    }
}
