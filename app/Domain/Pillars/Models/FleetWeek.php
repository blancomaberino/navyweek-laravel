<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\FleetWeekSeason;
use App\Domain\Pillars\Enums\FleetWeekStatus;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\FleetWeekFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A Fleet Week city guide (port of `fleetweek/*.ts`). One row drives a flexible
 * block template; `has_official_fleet_week` / `has_air_show` and `status` gate
 * which blocks render (Tier-3 cities set `has_official_fleet_week` false and omit
 * the festival/air-show payloads). Block payloads are cohesive display JSON; FAQs
 * and sources reuse the shared polymorphic tables.
 *
 * @property int $id
 * @property string $slug
 * @property string $city
 * @property string $state
 * @property string $state_abbr
 * @property int $year
 * @property string $branding_name
 * @property FleetWeekSeason $season
 * @property string $month_label
 * @property bool $has_official_fleet_week
 * @property bool $has_air_show
 * @property FleetWeekStatus $status
 * @property string $status_label editorial per-city text; may carry specifics beyond FleetWeekStatus::label()
 * @property string|null $status_note
 * @property string|null $festival_dates_label
 * @property string|null $airshow_dates_label
 * @property string $dek
 * @property array<int, string> $intro
 * @property array<int, array{label: string, value: string}> $quick_facts
 * @property string|null $official_url
 * @property string|null $official_site_label
 * @property array<int, array<string, mixed>> $schedule
 * @property string|null $schedule_note
 * @property array<string, mixed>|null $airshow
 * @property array<string, mixed>|null $parade_of_ships
 * @property array<string, mixed>|null $ship_tours
 * @property string|null $viewing_intro
 * @property array<int, array<string, mixed>> $viewing_spots
 * @property array<int, string> $getting_there
 * @property array<int, string> $history
 * @property array<int, array{year: string, note: string}>|null $past_years
 * @property array<string, mixed>|null $festival
 * @property string $card_summary
 * @property array<int, string>|null $related_slugs
 * @property string $meta_title
 * @property string $meta_description
 * @property string $h1
 * @property string $primary_keyword
 * @property string $og_image
 * @property Carbon $date_published
 * @property Carbon $date_modified
 * @property string $last_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Faq> $faqs
 * @property-read Collection<int, Source> $sources
 *
 * @method static FleetWeekFactory factory($count = null, $state = [])
 */
class FleetWeek extends Model
{
    /** @use HasFactory<FleetWeekFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'city',
        'state',
        'state_abbr',
        'year',
        'branding_name',
        'season',
        'month_label',
        'has_official_fleet_week',
        'has_air_show',
        'status',
        'status_label',
        'status_note',
        'festival_dates_label',
        'airshow_dates_label',
        'dek',
        'intro',
        'quick_facts',
        'official_url',
        'official_site_label',
        'schedule',
        'schedule_note',
        'airshow',
        'parade_of_ships',
        'ship_tours',
        'viewing_intro',
        'viewing_spots',
        'getting_there',
        'history',
        'past_years',
        'festival',
        'card_summary',
        'related_slugs',
        'meta_title',
        'meta_description',
        'h1',
        'primary_keyword',
        'og_image',
        'date_published',
        'date_modified',
        'last_verified',
    ];

    protected function casts(): array
    {
        return [
            'season' => FleetWeekSeason::class,
            'status' => FleetWeekStatus::class,
            'year' => 'integer',
            'has_official_fleet_week' => 'boolean',
            'has_air_show' => 'boolean',
            'intro' => 'array',
            'quick_facts' => 'array',
            'schedule' => 'array',
            'airshow' => 'array',
            'parade_of_ships' => 'array',
            'ship_tours' => 'array',
            'viewing_spots' => 'array',
            'getting_there' => 'array',
            'history' => 'array',
            'past_years' => 'array',
            'festival' => 'array',
            'related_slugs' => 'array',
            'date_published' => 'date',
            'date_modified' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return FleetWeekFactory
     */
    protected static function newFactory(): Factory
    {
        return FleetWeekFactory::new();
    }

    /**
     * FAQs for this fleet-week guide (shared polymorphic table), in order.
     *
     * @return MorphMany<Faq, $this>
     */
    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }

    /**
     * Primary-source citations for this guide (shared table), in order.
     *
     * @return MorphMany<Source, $this>
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(Source::class, 'sourceable')->orderBy('sort_order');
    }
}
