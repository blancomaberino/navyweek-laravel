<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\Placement;
use App\Domain\Crm\Models\Connection;
use Database\Factories\AffiliateLinkFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An outbound offer link, tagged with a placement sub-ID at render time.
 *
 * @property int $id
 * @property int|null $connection_id
 * @property int|null $offer_id
 * @property int $affiliate_network_id
 * @property string $base_url
 * @property Placement $placement
 * @property string $rel
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connection|null $connection
 * @property-read Offer|null $offer
 * @property-read AffiliateNetwork $network
 *
 * @method static AffiliateLinkFactory factory($count = null, $state = [])
 */
class AffiliateLink extends Model
{
    /** @use HasFactory<AffiliateLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'offer_id',
        'affiliate_network_id',
        'base_url',
        'placement',
        'rel',
    ];

    protected function casts(): array
    {
        return [
            'placement' => Placement::class,
        ];
    }

    /**
     * @return AffiliateLinkFactory
     */
    protected static function newFactory(): Factory
    {
        return AffiliateLinkFactory::new();
    }

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * @return BelongsTo<AffiliateNetwork, $this>
     */
    public function network(): BelongsTo
    {
        return $this->belongsTo(AffiliateNetwork::class, 'affiliate_network_id');
    }
}
