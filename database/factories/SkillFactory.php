<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Research\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    protected $model = Skill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->unique()->slug(2);

        return [
            'key' => $name,
            'name' => str($name)->headline()->value(),
            'current_version' => '1',
            'content_hash' => $this->faker->sha256(),
            'source_ref' => ".claude/skills/{$name}",
        ];
    }
}
