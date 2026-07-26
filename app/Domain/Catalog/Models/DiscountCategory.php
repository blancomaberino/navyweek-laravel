<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use Database\Factories\DiscountCategoryFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A discount CATEGORY hub (port of `discounts/categories.ts`). The hub at
 * `/discount/<slug>/` lists every Connection whose `category` equals
 * `match_category`. The three ordering overrides (`pinned`, `excluded`, `order`)
 * are soft slug lists of Connection slugs, resolved against the connection
 * registry at read time by EloquentDiscountCategoryRepository::orderedConnections
 * (the port of the legacy `orderCategoryDiscounts`), so they carry no DB
 * constraint. `intro` is the multi-paragraph lead — one <p> per element.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $match_category
 * @property string $meta_title
 * @property string $meta_description
 * @property string $h1
 * @property string $hero_tagline
 * @property array<int, string> $intro
 * @property string $og_image
 * @property array<int, string>|null $pinned
 * @property array<int, string>|null $excluded
 * @property array<int, string>|null $order
 * @property Carbon $date_published
 * @property Carbon $date_modified
 * @property string $last_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static DiscountCategoryFactory factory($count = null, $state = [])
 */
class DiscountCategory extends Model
{
    /** @use HasFactory<DiscountCategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'match_category',
        'meta_title',
        'meta_description',
        'h1',
        'hero_tagline',
        'intro',
        'og_image',
        'pinned',
        'excluded',
        'order',
        'date_published',
        'date_modified',
        'last_verified',
    ];

    protected function casts(): array
    {
        return [
            'intro' => 'array',
            'pinned' => 'array',
            'excluded' => 'array',
            'order' => 'array',
            'date_published' => 'date',
            'date_modified' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return DiscountCategoryFactory
     */
    protected static function newFactory(): Factory
    {
        return DiscountCategoryFactory::new();
    }
}
