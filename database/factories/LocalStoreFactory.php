<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\LocalDiscount;
use App\Domain\Catalog\Models\LocalStore;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalStore>
 */
class LocalStoreFactory extends Factory
{
    protected $model = LocalStore::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'local_discount_id' => LocalDiscount::factory(),
            'name' => fake()->company().' — Main Location',
            'street' => fake()->streetAddress(),
            'city' => 'Houston',
            'state_abbr' => 'TX',
            'zip' => fake()->postcode(),
            'phone' => fake()->phoneNumber(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'directions_url' => null,
            'map_embed_url' => null,
            'distance_label' => null,
            'sort_order' => 0,
        ];
    }
}
