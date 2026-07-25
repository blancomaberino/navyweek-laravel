<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = $this->faker->unique()->slug();

        // Only the columns the factory must populate. The nullable SEO columns and
        // the migration defaults (og_type=website, noindex=false) are left to the
        // DB so the factory can't silently pin a stale default. `is_published` is
        // set because the DB default is false and published is the common case.
        return [
            'page_type' => PageType::Static,
            'slug' => $slug,
            'url_path' => "/{$slug}/",
            'title' => $this->faker->sentence(),
            'meta_description' => $this->faker->sentence(12),
            'is_published' => true,
        ];
    }

    /**
     * A hidden (draft) page — routing exists but the catch-all 301s it to "/".
     */
    public function unpublished(): static
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }
}
