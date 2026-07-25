<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Models\UsState;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UsState>
 */
class UsStateFactory extends Factory
{
    protected $model = UsState::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->state();

        return [
            'slug' => str($name)->slug()->value(),
            'name' => $name,
            'abbr' => strtoupper(fake()->unique()->lexify('??')),
        ];
    }
}
