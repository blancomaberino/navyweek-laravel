<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A keyword-variant slug that resolves to a canonical Connection. Replaces the
 * legacy `pipeline/queue/aliases.json` map.
 *
 * @property int $id
 * @property string $alias_slug
 * @property int $connection_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connection $connection
 */
class ConnectionAlias extends Model
{
    protected $fillable = [
        'alias_slug',
        'connection_id',
    ];

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }
}
