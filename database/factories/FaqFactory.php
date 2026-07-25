<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Publishing\Models\Page;
use App\Domain\Shared\Models\Faq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faq>
 */
class FaqFactory extends Factory
{
    protected $model = Faq::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Defaults to a Page FAQ; override via ->for($model, 'faqable').
        return [
            'faqable_type' => (new Page)->getMorphClass(),
            'faqable_id' => Page::factory(),
            'question' => 'Does this brand offer a military discount?',
            'answer' => 'Yes — verify eligibility through the official program.',
            'sort_order' => 0,
        ];
    }
}
