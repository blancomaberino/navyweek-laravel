<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\LocalStoreHoursFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One opening-hours span for a LocalStore (port of the legacy `OpeningHours`).
 * `days` is the human label (e.g. "Mon–Sun"); `day_of_week` is the matching
 * schema.org day-name list for the same span.
 *
 * @property int $id
 * @property int $local_store_id
 * @property string $days
 * @property array<int, string> $day_of_week
 * @property string $opens
 * @property string $closes
 * @property string|null $note
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read LocalStore $localStore
 *
 * @method static LocalStoreHoursFactory factory($count = null, $state = [])
 */
class LocalStoreHours extends Model
{
    /** @use HasFactory<LocalStoreHoursFactory> */
    use HasFactory;

    protected $table = 'local_store_hours';

    protected $fillable = [
        'local_store_id',
        'days',
        'day_of_week',
        'opens',
        'closes',
        'note',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return LocalStoreHoursFactory
     */
    protected static function newFactory(): Factory
    {
        return LocalStoreHoursFactory::new();
    }

    /**
     * @return BelongsTo<LocalStore, $this>
     */
    public function localStore(): BelongsTo
    {
        return $this->belongsTo(LocalStore::class);
    }
}
