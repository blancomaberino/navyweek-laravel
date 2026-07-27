<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\JetTeamStatus;
use Database\Factories\JetTeamScheduleRowFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One stop on a team's official season tour (port of `JetTeamScheduleRow`) —
 * factual schedule data for the hub table. A stop does NOT imply a published
 * city guide; `slug` links only when a guide with that slug is published. Not
 * unique — a city can appear twice in one season.
 *
 * @property int $id
 * @property int $jet_team_id
 * @property string $dates_label
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $city
 * @property string $state
 * @property string $show
 * @property string|null $venue
 * @property Admission|null $admission
 * @property JetTeamStatus $status
 * @property string $slug
 * @property string|null $guide_label
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read JetTeam $team
 *
 * @method static JetTeamScheduleRowFactory factory($count = null, $state = [])
 */
class JetTeamScheduleRow extends Model
{
    /** @use HasFactory<JetTeamScheduleRowFactory> */
    use HasFactory;

    protected $table = 'jet_team_schedule';

    protected $fillable = [
        'jet_team_id',
        'dates_label',
        'start_date',
        'end_date',
        'city',
        'state',
        'show',
        'venue',
        'admission',
        'status',
        'slug',
        'guide_label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'admission' => Admission::class,
            'status' => JetTeamStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return JetTeamScheduleRowFactory
     */
    protected static function newFactory(): Factory
    {
        return JetTeamScheduleRowFactory::new();
    }

    /**
     * @return BelongsTo<JetTeam, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(JetTeam::class, 'jet_team_id');
    }
}
