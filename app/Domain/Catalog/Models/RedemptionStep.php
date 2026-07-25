<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\RedemptionChannel;
use Database\Factories\RedemptionStepFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A numbered redemption step on an offer (was `redeemOnline[]` / `redeemInStore[]`,
 * merged and discriminated by `channel`).
 *
 * @property int $id
 * @property int $offer_id
 * @property RedemptionChannel $channel
 * @property string $title
 * @property string $detail
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Offer $offer
 *
 * @method static RedemptionStepFactory factory($count = null, $state = [])
 */
class RedemptionStep extends Model
{
    /** @use HasFactory<RedemptionStepFactory> */
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'channel',
        'title',
        'detail',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'channel' => RedemptionChannel::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return RedemptionStepFactory
     */
    protected static function newFactory(): Factory
    {
        return RedemptionStepFactory::new();
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
