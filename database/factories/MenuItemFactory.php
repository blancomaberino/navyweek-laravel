<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = fake()->unique()->words(2, true);

        return [
            'menu_id' => Menu::factory(),
            'parent_id' => null,
            'label' => ucfirst($label),
            'url' => '/'.str($label)->slug()->value().'/',
            'target' => null,
            'rel' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function childOf(MenuItem $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'menu_id' => $parent->menu_id,
            'parent_id' => $parent->id,
        ]);
    }
}
