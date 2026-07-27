<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\LocalStore;
use App\Domain\Catalog\Models\LocalStoreHours;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LocalStoreHours>
 */
class LocalStoreHoursFactory extends Factory
{
    protected $model = LocalStoreHours::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'local_store_id' => LocalStore::factory(),
            'days' => 'Mon–Sun',
            'day_of_week' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
            'opens' => '09:00',
            'closes' => '17:00',
            'note' => null,
            'sort_order' => 0,
        ];
    }
}
