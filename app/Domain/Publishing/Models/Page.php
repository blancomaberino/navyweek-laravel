<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Models;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Shared\Concerns\HasFaqs;
use App\Domain\Shared\Concerns\HasSources;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use App\Models\User;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int|null $author_id
 * @property int|null $reviewer_id
 * @property string|null $pageable_type
 * @property int|null $pageable_id
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $pageable
 * @property-read User|null $author
 * @property-read User|null $reviewer
 * @property-read Collection<int, Source> $sources
 * @property-read Collection<int, Faq> $faqs
 *
 * @method static PageFactory factory($count = null, $state = [])
 */
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasFaqs;
    use HasSources;

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
        'author_id',
        'reviewer_id',
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

    /**
     * The byline author — a `users` row, assignable from the admin panel. The
     * discount-guide Article `author` Person node is built from this user.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * The reviewer who verified the page — a `users` row, assignable from the admin
     * panel. Drives the WebPage `reviewedBy` Person node.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
