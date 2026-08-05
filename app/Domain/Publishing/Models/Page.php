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
 * A public route served at the canonical `url_path`. Carries the SEO / JSON-LD head
 * meta and points at the aggregate it presents via the polymorphic `pageable`.
 *
 * Identity vs. location: a generated page is identified by its stable `generation_key`
 * (assigned by `pages:generate-*`), while `url_path` is mutable — an editor can rename
 * it (`url_path_is_custom` = true, preserved across regeneration) and a family-wide
 * prefix change (config('publishing.paths.*')) moves every non-custom page.
 *
 * @property int $id
 * @property PageType $page_type
 * @property string $slug
 * @property string|null $generation_key
 * @property string $url_path
 * @property bool $url_path_is_custom
 * @property string|null $title
 * @property string|null $h1
 * @property string|null $eyebrow
 * @property string|null $meta_description
 * @property string|null $canonical_path
 * @property string $og_type
 * @property string|null $og_image_path
 * @property bool $noindex
 * @property Carbon|null $date_published
 * @property Carbon|null $date_modified
 * @property array<int, array<string, mixed>>|null $json_ld
 * @property array<int, array<string, mixed>>|null $body_blocks
 * @property Carbon|null $last_reviewed
 * @property Carbon|null $sources_checked
 * @property array<string, mixed>|null $key_facts
 * @property string|null $disclosure
 * @property string|null $editorial_source_priority
 * @property string|null $editorial_review_cadence
 * @property string|null $trust_page_label
 * @property bool $shows_reference_backlink
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
        'generation_key',
        'url_path',
        'url_path_is_custom',
        'title',
        'h1',
        'eyebrow',
        'meta_description',
        'canonical_path',
        'og_type',
        'og_image_path',
        'noindex',
        'date_published',
        'date_modified',
        'json_ld',
        'body_blocks',
        'last_reviewed',
        'sources_checked',
        'key_facts',
        'disclosure',
        'editorial_source_priority',
        'editorial_review_cadence',
        'trust_page_label',
        'shows_reference_backlink',
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
            'url_path_is_custom' => 'boolean',
            'date_published' => 'datetime',
            'date_modified' => 'datetime',
            'json_ld' => 'array',
            'body_blocks' => 'array',
            'last_reviewed' => 'date',
            'sources_checked' => 'date',
            'key_facts' => 'array',
            'shows_reference_backlink' => 'boolean',
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
