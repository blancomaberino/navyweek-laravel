<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Models\OverseasCountry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OverseasCountry>
 */
class OverseasCountryFactory extends Factory
{
    protected $model = OverseasCountry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->country();

        return [
            'slug' => str($name)->slug()->value(),
            'name' => $name,
            'iso2' => strtoupper(fake()->unique()->lexify('??')),
            'region' => fake()->randomElement(CombatantCommand::cases()),
            'is_us_territory' => false,
        ];
    }
}
