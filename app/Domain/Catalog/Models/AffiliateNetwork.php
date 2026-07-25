<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\AffiliateNetworkFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An affiliate network in the sub-ID registry (port of `NETWORK_SUBID_REGISTRY`).
 * `subid_param` is the query key that carries a placement token on outbound links.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subid_param
 * @property array<string, string>|null $extra_params
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, AffiliateLink> $links
 *
 * @method static AffiliateNetworkFactory factory($count = null, $state = [])
 */
class AffiliateNetwork extends Model
{
    /** @use HasFactory<AffiliateNetworkFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'subid_param',
        'extra_params',
    ];

    protected function casts(): array
    {
        return [
            'extra_params' => 'array',
        ];
    }

    /**
     * @return AffiliateNetworkFactory
     */
    protected static function newFactory(): Factory
    {
        return AffiliateNetworkFactory::new();
    }

    /**
     * @return HasMany<AffiliateLink, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }
}
