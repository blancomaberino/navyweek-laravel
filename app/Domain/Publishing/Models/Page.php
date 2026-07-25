<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Publishing\Enums\PageType;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A public route, keyed on the canonical `url_path`. Carries the SEO / JSON-LD head
 * meta and points at the aggregate it presents via the polymorphic `pageable`.
 *
 * @property int $id
 * @property PageType $page_type
 * @property string $slug
 * @property string $url_path
 * @property string|null $title
 * @property string|null $meta_description
 * @property string|null $canonical_path
 * @property string $og_type
 * @property string|null $og_image_path
 * @property bool $noindex
 * @property Carbon|null $date_published
 * @property Carbon|null $date_modified
 * @property array<int, array<string, mixed>>|null $json_ld
 * @property string|null $pageable_type
 * @property int|null $pageable_id
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $pageable
 *
 * @method static PageFactory factory($count = null, $state = [])
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    protected $fillable = [
        'page_type',
        'slug',
        'url_path',
        'title',
        'meta_description',
        'canonical_path',
        'og_type',
        'og_image_path',
        'noindex',
        'date_published',
        'date_modified',
        'json_ld',
        'pageable_type',
        'pageable_id',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'page_type' => PageType::class,
            'noindex' => 'boolean',
            'date_published' => 'datetime',
            'date_modified' => 'datetime',
            'json_ld' => 'array',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Explicit factory resolution: the default guesser looks under
     * `Database\Factories\Domain\Publishing\Models\…`, which doesn't exist for
     * Domain-namespaced models, so we point it at the flat factory.
     *
     * @return PageFactory
     */
    protected static function newFactory(): Factory
    {
        return PageFactory::new();
    }

    /**
     * The aggregate this page presents — an Offer, Connection, or pillar entity.
     * Null for static pages and hubs that own no aggregate.
     *
     * @return MorphTo<Model, $this>
     */
    public function pageable(): MorphTo
    {
        return $this->morphTo();
    }
}
