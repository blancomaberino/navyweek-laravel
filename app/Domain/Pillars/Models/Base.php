<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\BaseType;
use App\Domain\Pillars\Enums\CombatantCommand;
use App\Domain\Pillars\Enums\RegionType;
use App\Domain\Shared\Concerns\HasFaqs;
use App\Domain\Shared\Concerns\HasSources;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\BaseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A naval base / installation — the first reference pillar (port of `bases/*.ts`).
 * `region_type` discriminates a state-based (CONUS/Hawaii) base from an overseas
 * (country/territory) one, deciding which column group is populated. FAQs and
 * sources hang off the shared polymorphic tables; cohesive display lists are JSON.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property array<int, string>|null $aka
 * @property BaseType $type
 * @property RegionType $region_type
 * @property string|null $state
 * @property string|null $state_name
 * @property string|null $state_abbr
 * @property string|null $country
 * @property string|null $country_slug
 * @property string|null $country_iso2
 * @property CombatantCommand|null $region
 * @property string|null $host_nation
 * @property string|null $timezone
 * @property string|null $local_currency
 * @property array<int, string>|null $local_language
 * @property string|null $sofa_status
 * @property bool|null $command_sponsorship_required
 * @property bool|null $passport_required
 * @property string $city
 * @property string|null $county
 * @property string $lat
 * @property string $lng
 * @property int $established
 * @property string|null $personnel_count
 * @property string|null $area_acres
 * @property array<int, string> $major_units
 * @property array<int, array{label: string, value: string}> $key_facts
 * @property string $meta_title
 * @property string $meta_description
 * @property string $h1
 * @property string $hero_tagline
 * @property string $seo_keyword_primary
 * @property string|null $commanding_officer
 * @property string|null $motto
 * @property string|null $nickname
 * @property string|null $wikipedia_url
 * @property string|null $official_url
 * @property array<int, array<string, mixed>>|null $notable_events
 * @property array<int, string>|null $nearby_bases
 * @property string|null $nearest_fleet_week_slug
 * @property string $overview
 * @property string $history
 * @property string|null $location_context
 * @property string|null $host_nation_context
 * @property Carbon $last_updated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UsState|null $usState
 * @property-read OverseasCountry|null $overseasCountry
 * @property-read Collection<int, Faq> $faqs
 * @property-read Collection<int, Source> $sources
 *
 * @method static BaseFactory factory($count = null, $state = [])
 */
class Base extends Model
{
    /** @use HasFactory<BaseFactory> */
    use HasFactory;

    use HasFaqs;
    use HasSources;

    protected $fillable = [
        'slug',
        'name',
        'aka',
        'type',
        'region_type',
        'state',
        'state_name',
        'state_abbr',
        'country',
        'country_slug',
        'country_iso2',
        'region',
        'host_nation',
        'timezone',
        'local_currency',
        'local_language',
        'sofa_status',
        'command_sponsorship_required',
        'passport_required',
        'city',
        'county',
        'lat',
        'lng',
        'established',
        'personnel_count',
        'area_acres',
        'major_units',
        'key_facts',
        'meta_title',
        'meta_description',
        'h1',
        'hero_tagline',
        'seo_keyword_primary',
        'commanding_officer',
        'motto',
        'nickname',
        'wikipedia_url',
        'official_url',
        'notable_events',
        'nearby_bases',
        'nearest_fleet_week_slug',
        'overview',
        'history',
        'location_context',
        'host_nation_context',
        'last_updated',
    ];

    protected function casts(): array
    {
        return [
            'type' => BaseType::class,
            'region_type' => RegionType::class,
            'region' => CombatantCommand::class,
            'aka' => 'array',
            'local_language' => 'array',
            'major_units' => 'array',
            'key_facts' => 'array',
            'notable_events' => 'array',
            'nearby_bases' => 'array',
            'command_sponsorship_required' => 'boolean',
            'passport_required' => 'boolean',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'established' => 'integer',
            'last_updated' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser (it looks under
     * Database\Factories\Domain\…); point at the flat factory explicitly.
     *
     * @return BaseFactory
     */
    protected static function newFactory(): Factory
    {
        return BaseFactory::new();
    }

    /** OCONUS = foreign country or U.S. territory. Delegates to the discriminator. */
    public function isOverseas(): bool
    {
        return $this->region_type->isOverseas();
    }

    /**
     * The U.S. state this base sits in (state-based bases only), joined by slug.
     *
     * @return BelongsTo<UsState, $this>
     */
    public function usState(): BelongsTo
    {
        return $this->belongsTo(UsState::class, 'state', 'slug');
    }

    /**
     * The overseas host country (overseas bases only), joined by slug.
     *
     * @return BelongsTo<OverseasCountry, $this>
     */
    public function overseasCountry(): BelongsTo
    {
        return $this->belongsTo(OverseasCountry::class, 'country_slug', 'slug');
    }
}
