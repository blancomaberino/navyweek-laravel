<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use App\Domain\Shared\Models\Faq;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model the shared polymorphic FAQ relation (`faqable` → `faqs`), ordered
 * by `sort_order`. Extracted from the models that each hand-copied this identical
 * relation. The using model keeps its own `@property-read ... $faqs` docblock.
 *
 * @mixin Model
 *
 * @phpstan-require-extends Model
 */
trait HasFaqs
{
    /**
     * FAQs attached to this model (shared polymorphic table), in display order.
     *
     * @return MorphMany<Faq, $this>
     */
    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }
}
