<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\LocalStoreFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A physical storefront for a LocalDiscount (port of the legacy `LocalStore`).
 * The first store (`sort_order` 0) is the primary that drives the NAP block and
 * LocalBusiness schema. Opening hours are the child `local_store_hours` rows.
 *
 * @property int $id
 * @property int $local_discount_id
 * @property string $name
 * @property string $street
 * @property string $city
 * @property string $state_abbr
 * @property string $zip
 * @property string|null $phone
 * @property string $lat
 * @property string $lng
 * @property string|null $directions_url
 * @property string|null $map_embed_url
 * @property string|null $distance_label
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LocalDiscount $localDiscount
 * @property-read Collection<int, LocalStoreHours> $hours
 *
 * @method static LocalStoreFactory factory($count = null, $state = [])
 */
class LocalStore extends Model
{
    /** @use HasFactory<LocalStoreFactory> */
    use HasFactory;

    protected $fillable = [
        'local_discount_id',
        'name',
        'street',
        'city',
        'state_abbr',
        'zip',
        'phone',
        'lat',
        'lng',
        'directions_url',
        'map_embed_url',
        'distance_label',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return LocalStoreFactory
     */
    protected static function newFactory(): Factory
    {
        return LocalStoreFactory::new();
    }

    /**
     * @return BelongsTo<LocalDiscount, $this>
     */
    public function localDiscount(): BelongsTo
    {
        return $this->belongsTo(LocalDiscount::class);
    }

    /**
     * Opening-hours spans, in display order (mapped to openingHoursSpecification).
     *
     * @return HasMany<LocalStoreHours, $this>
     */
    public function hours(): HasMany
    {
        return $this->hasMany(LocalStoreHours::class)->orderBy('sort_order');
    }
}
