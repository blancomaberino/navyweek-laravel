<?php

declare(strict_types=1);

namespace App\Domain\Shared\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A question/answer pair, attached polymorphically to a Page or Offer (later
 * bases/ranks/…). The single source for both the rendered FAQ and its FAQPage
 * JSON-LD, so the hard schema↔content parity gate compares them against one row set.
 *
 * @property int $id
 * @property string $faqable_type
 * @property int $faqable_id
 * @property string $question
 * @property string $answer
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $faqable
 *
 * @method static FaqFactory factory($count = null, $state = [])
 */
class Faq extends Model
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory;

    protected $fillable = [
        'faqable_type',
        'faqable_id',
        'question',
        'answer',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return FaqFactory
     */
    protected static function newFactory(): Factory
    {
        return FaqFactory::new();
    }

    /**
     * The entity this FAQ belongs to (Page / Offer / pillar).
     *
     * @return MorphTo<Model, $this>
     */
    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }
}
