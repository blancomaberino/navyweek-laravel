<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\CombatantCommand;
use Database\Factories\OverseasCountryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An overseas host country (or country-equivalent U.S. territory, e.g. Guam) that
 * hosts OCONUS installations — the lookup the overseas base hubs group on
 * (`/bases/<country>/`). Port of `bases/countries.ts`.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $iso2
 * @property CombatantCommand $region
 * @property bool $is_us_territory
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Base> $bases
 *
 * @method static OverseasCountryFactory factory($count = null, $state = [])
 */
class OverseasCountry extends Model
{
    /** @use HasFactory<OverseasCountryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'iso2',
        'region',
        'is_us_territory',
    ];

    protected function casts(): array
    {
        return [
            'region' => CombatantCommand::class,
            'is_us_territory' => 'boolean',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser (it looks under
     * Database\Factories\Domain\…); point at the flat factory explicitly.
     *
     * @return OverseasCountryFactory
     */
    protected static function newFactory(): Factory
    {
        return OverseasCountryFactory::new();
    }

    /**
     * Bases located in this country (joined on the `country_slug` slug).
     *
     * @return HasMany<Base, $this>
     */
    public function bases(): HasMany
    {
        return $this->hasMany(Base::class, 'country_slug', 'slug');
    }
}
