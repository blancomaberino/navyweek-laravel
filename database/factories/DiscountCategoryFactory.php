<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Catalog\Models\DiscountCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountCategory>
 */
class DiscountCategoryFactory extends Factory
{
    protected $model = DiscountCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // words(3, true) draws from a far larger space than the ~180-word unique
        // pool of word(), avoiding OverflowException across many-row test runs.
        $name = ucfirst(fake()->unique()->words(3, true));
        $slug = str($name)->slug()->value().'-military-veteran';

        return [
            'slug' => $slug,
            'name' => $name,
            'match_category' => $name,
            'meta_title' => $name.' Military Discounts',
            'meta_description' => 'The '.$name.' military discounts worth using, compared.',
            'h1' => $name.' With Military Discounts',
            'hero_tagline' => 'Browse the '.$name.' brands we have researched.',
            'intro' => ['A lead paragraph.', 'A second paragraph.'],
            'og_image' => '/og/discount/category-'.$slug.'.png',
            'pinned' => null,
            'excluded' => null,
            'order' => null,
            'date_published' => '2026-07-10',
            'date_modified' => '2026-07-10',
            'last_verified' => 'July 10, 2026',
        ];
    }
}
