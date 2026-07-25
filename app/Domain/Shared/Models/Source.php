<?php

declare(strict_types=1);

namespace App\Domain\Shared\Models;

use App\Domain\Shared\Enums\ConfidenceLevel;
use App\Domain\Shared\Enums\SourceType;
use Database\Factories\SourceFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A primary-source citation, attached polymorphically to whatever it substantiates
 * (an Offer key fact, a Research brief, a Page). The shared backbone of the YMYL
 * "every claim traces to a verified source" invariant.
 *
 * @property int $id
 * @property string $sourceable_type
 * @property int $sourceable_id
 * @property string $label
 * @property string $url
 * @property string|null $publisher
 * @property SourceType|null $source_type
 * @property Carbon|null $accessed_at
 * @property ConfidenceLevel|null $confidence
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $sourceable
 *
 * @method static SourceFactory factory($count = null, $state = [])
 */
class Source extends Model
{
    /** @use HasFactory<SourceFactory> */
    use HasFactory;

    protected $fillable = [
        'sourceable_type',
        'sourceable_id',
        'label',
        'url',
        'publisher',
        'source_type',
        'accessed_at',
        'confidence',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'accessed_at' => 'date',
            'source_type' => SourceType::class,
            'confidence' => ConfidenceLevel::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return SourceFactory
     */
    protected static function newFactory(): Factory
    {
        return SourceFactory::new();
    }

    /**
     * The aggregate this citation substantiates (Offer / Research / Page).
     *
     * @return MorphTo<Model, $this>
     */
    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
