<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\NavyWeekStatus;
use App\Domain\Shared\Concerns\HasFaqs;
use App\Domain\Shared\Concerns\HasSources;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\NavyWeekEventFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A Navy Week stop (port of the legacy `NavyWeekEvent` + `CityData` + `CityExtras`,
 * folded into one row keyed by slug). `sequence` preserves the legacy numeric id
 * (the canonical 1..N ordering). The rich city-detail block (venues, daily
 * schedule, context) is optional; FAQs and official sources hang off the shared
 * polymorphic tables.
 *
 * @property int $id
 * @property int $sequence
 * @property string $slug
 * @property string $city
 * @property string $state
 * @property string $state_abbr
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $anchor_event
 * @property string $lat
 * @property string $lng
 * @property bool $first_time
 * @property bool|null $first_time_location
 * @property string|null $first_time_badge
 * @property NavyWeekStatus $status
 * @property array<int, string>|null $description
 * @property string|null $anchor_event_detail
 * @property string|null $anchor_event_url
 * @property string|null $first_time_note
 * @property array<int, string>|null $navy_assets
 * @property array<int, string>|null $key_venues
 * @property array<int, string>|null $military_context
 * @property string|null $navco_url
 * @property array<int, string>|null $highlights
 * @property array<int, array<string, mixed>>|null $venues
 * @property array<int, array<string, mixed>>|null $daily_schedule
 * @property string|null $parking_notes
 * @property string|null $cost_summary
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Faq> $faqs
 * @property-read Collection<int, Source> $sources
 *
 * @method static NavyWeekEventFactory factory($count = null, $state = [])
 */
class NavyWeekEvent extends Model
{
    /** @use HasFactory<NavyWeekEventFactory> */
    use HasFactory;

    use HasFaqs;
    use HasSources;

    protected $fillable = [
        'sequence',
        'slug',
        'city',
        'state',
        'state_abbr',
        'start_date',
        'end_date',
        'anchor_event',
        'lat',
        'lng',
        'first_time',
        'first_time_location',
        'first_time_badge',
        'status',
        'description',
        'anchor_event_detail',
        'anchor_event_url',
        'first_time_note',
        'navy_assets',
        'key_venues',
        'military_context',
        'navco_url',
        'highlights',
        'venues',
        'daily_schedule',
        'parking_notes',
        'cost_summary',
        'last_verified_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => NavyWeekStatus::class,
            'sequence' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'first_time' => 'boolean',
            'first_time_location' => 'boolean',
            'description' => 'array',
            'navy_assets' => 'array',
            'key_venues' => 'array',
            'military_context' => 'array',
            'highlights' => 'array',
            'venues' => 'array',
            'daily_schedule' => 'array',
            'last_verified_at' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return NavyWeekEventFactory
     */
    protected static function newFactory(): Factory
    {
        return NavyWeekEventFactory::new();
    }

    /**
     * Whether this stop counts toward the "first-time locations" total — a full
     * first-time host city OR a stop introducing a new first-time location. Port
     * of the legacy `isFirstTimeLocation`.
     */
    public function isFirstTimeLocation(): bool
    {
        return $this->first_time || (bool) $this->first_time_location;
    }

    /**
     * This stop's date range as a display label — "September 26 – 28, 2026" within one
     * month, else "September 26 – October 3, 2026" (en-dash, surrounding spaces; start
     * year for both). Port of `formatDateRange` (data.ts); the single home for the rule,
     * reused by the city JSON-LD (NavyWeekCitySchema) and the home landing view.
     */
    public function dateRangeLabel(): string
    {
        $start = $this->start_date;
        $end = $this->end_date;

        return $start->month === $end->month
            ? $start->format('F j').' – '.$end->format('j').', '.$start->format('Y')
            : $start->format('F j').' – '.$end->format('F j').', '.$start->format('Y');
    }
}
