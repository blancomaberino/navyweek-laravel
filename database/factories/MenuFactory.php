<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Menu>
 */
class MenuFactory extends Factory
{
    protected $model = Menu::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'key' => str($name)->slug()->value(),
            'name' => ucfirst($name),
            'location' => MenuLocation::Header,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function location(MenuLocation $location): static
    {
        return $this->state(fn (array $attributes): array => ['location' => $location]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
