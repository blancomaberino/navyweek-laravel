<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Enums\ConfidenceLevel;
use App\Domain\Research\Enums\ResearchedBy;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Research\Models\Research;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Research>
 */
class ResearchFactory extends Factory
{
    protected $model = Research::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'connection_id' => Connection::factory(),
            'brief_path' => 'research/discounts/example.md',
            'raw_markdown' => "# Example Brief\n\n## Executive Summary\n\n".$this->faker->paragraph(),
            'executive_summary' => $this->faker->paragraph(),
            'verified_facts' => [
                ['fact' => 'Everyday discount', 'value' => '10% off', 'source' => 'example.com', 'accessed' => '2026-06-23', 'confidence' => 'High'],
            ],
            'confidence_overall' => ConfidenceLevel::High,
            'last_verified' => '2026-06-23',
            'researched_by' => ResearchedBy::ClaudePipeline,
            'skill_key' => 'military-discount-research',
            'skill_version' => '1',
            'status' => ResearchStatus::Complete,
            'version' => 1,
        ];
    }

    /** A draft brief produced by a human editor. */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => ResearchStatus::Draft,
            'researched_by' => ResearchedBy::Human,
        ]);
    }

    /** A prior, superseded version. */
    public function superseded(): static
    {
        return $this->state(fn (): array => [
            'status' => ResearchStatus::Superseded,
        ]);
    }
}
