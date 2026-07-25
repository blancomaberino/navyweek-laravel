<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crm\Enums\Audience;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connection>
 */
class ConnectionFactory extends Factory
{
    protected $model = Connection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brand = $this->faker->unique()->company();
        $slug = str($brand)->slug()->value();

        return [
            'slug' => $slug,
            'brand' => $brand,
            'key' => $slug,
            'category' => $this->faker->randomElement(['Technology', 'Footwear', 'Retailers', 'Finance']),
            'status' => ConnectionStatus::Pending,
            'priority_tier' => $this->faker->numberBetween(1, 4),
            'is_backlog' => false,
            'max_volume' => $this->faker->numberBetween(0, 50_000),
            'total_volume' => $this->faker->numberBetween(0, 80_000),
            'keyword_count' => $this->faker->numberBetween(0, 200),
            'min_difficulty' => $this->faker->numberBetween(0, 90),
            'cpc' => $this->faker->randomFloat(2, 0, 10),
            'top_keyword' => "{$brand} military discount",
            'audiences' => [Audience::Military->value, Audience::Veteran->value],
            'research_cadence_days' => 45,
        ];
    }

    /** A backlog brand (imported pending, low priority). */
    public function backlog(): static
    {
        return $this->state(fn (): array => ['is_backlog' => true]);
    }

    /** A published brand with a live page. */
    public function published(): static
    {
        return $this->state(fn (): array => ['status' => ConnectionStatus::Published]);
    }

    /** Research due for re-verification as of the given date. */
    public function dueForReview(string $date): static
    {
        return $this->state(fn (): array => [
            'last_verified_at' => $date,
            'next_review_due' => $date,
        ]);
    }
}
