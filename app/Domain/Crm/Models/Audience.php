<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Enums\Audience as AudienceEnum;
use Database\Factories\AudienceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A first-class audience (eligible cohort) an Offer can target — the joinable form
 * of the {@see AudienceEnum} vocabulary. Seeded from the enum (AudienceSeeder);
 * `key` casts back to it so callers get a type-safe value + `label()`.
 *
 * @property int $id
 * @property AudienceEnum $key
 * @property string $label
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Offer> $offers
 *
 * @method static AudienceFactory factory($count = null, $state = [])
 */
class Audience extends Model
{
    /** @use HasFactory<AudienceFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'key' => AudienceEnum::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the flat
     * factory explicitly.
     *
     * @return AudienceFactory
     */
    protected static function newFactory(): Factory
    {
        return AudienceFactory::new();
    }

    /**
     * Offers that serve this audience.
     *
     * @return BelongsToMany<Offer, $this>
     */
    public function offers(): BelongsToMany
    {
        return $this->belongsToMany(Offer::class, 'offer_audience');
    }
}
