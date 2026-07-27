<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamScheduleRow;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JetTeamScheduleRow>
 */
class JetTeamScheduleRowFactory extends Factory
{
    protected $model = JetTeamScheduleRow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $city = fake()->city();

        return [
            'jet_team_id' => JetTeam::factory(),
            'dates_label' => 'Aug 8–9',
            'start_date' => '2026-08-08',
            'end_date' => '2026-08-09',
            'city' => $city,
            'state' => 'AK',
            'show' => 'Arctic Thunder',
            'venue' => 'Branson Airport',
            'admission' => Admission::Free,
            'status' => JetTeamStatus::Scheduled,
            'slug' => str($city)->slug()->value(),
            'guide_label' => null,
            'sort_order' => 0,
        ];
    }
}
