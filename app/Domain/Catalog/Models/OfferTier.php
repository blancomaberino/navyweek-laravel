<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\OfferTierFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A per-audience savings row on an offer (was the legacy `tiers[]`).
 *
 * @property int $id
 * @property int $offer_id
 * @property string $audience
 * @property string $amount
 * @property string|null $note
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Offer $offer
 *
 * @method static OfferTierFactory factory($count = null, $state = [])
 */
class OfferTier extends Model
{
    /** @use HasFactory<OfferTierFactory> */
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'audience',
        'amount',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return OfferTierFactory
     */
    protected static function newFactory(): Factory
    {
        return OfferTierFactory::new();
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
