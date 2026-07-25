<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\OfferType;
use App\Domain\Catalog\Enums\VerificationProvider;
use App\Domain\Crm\Models\Audience;
use App\Domain\Crm\Models\Connection;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\OfferFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A discount offer carried by a Connection — the second of the four lifecycles
 * (Connection → Offer → Page → Research). Many offers per connection.
 *
 * @property int $id
 * @property int $connection_id
 * @property string|null $internal_label
 * @property OfferType $offer_type
 * @property string|null $headline_discount
 * @property string|null $discount_summary
 * @property VerificationProvider|null $verification
 * @property string|null $verification_url
 * @property string|null $official_url
 * @property string|null $audience_label
 * @property array<array-key, mixed>|null $eligibility
 * @property array<array-key, mixed>|null $exclusions
 * @property array<array-key, mixed>|null $key_facts
 * @property array<array-key, mixed>|null $promo
 * @property array<array-key, mixed>|null $savings_hack
 * @property array<array-key, mixed>|null $savings_table
 * @property array<array-key, mixed>|null $savings_table_secondary
 * @property array<array-key, mixed>|null $chooser
 * @property array<array-key, mixed>|null $share_cta
 * @property string|null $cta_label
 * @property string|null $cta_subnote
 * @property string|null $source_priority_note
 * @property string|null $sticky_cta_label
 * @property bool $is_primary
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connection $connection
 * @property-read Collection<int, OfferTier> $tiers
 * @property-read Collection<int, RedemptionStep> $redemptionSteps
 * @property-read Collection<int, AffiliateLink> $affiliateLinks
 * @property-read Collection<int, Audience> $audiences
 * @property-read Collection<int, Source> $sources
 * @property-read Collection<int, Faq> $faqs
 *
 * @method static OfferFactory factory($count = null, $state = [])
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected $fillable = [
        'connection_id',
        'internal_label',
        'offer_type',
        'headline_discount',
        'discount_summary',
        'verification',
        'verification_url',
        'official_url',
        'audience_label',
        'eligibility',
        'exclusions',
        'key_facts',
        'promo',
        'savings_hack',
        'savings_table',
        'savings_table_secondary',
        'chooser',
        'share_cta',
        'cta_label',
        'cta_subnote',
        'source_priority_note',
        'sticky_cta_label',
        'is_primary',
        'sort_order',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'offer_type' => OfferType::class,
            'verification' => VerificationProvider::class,
            'eligibility' => 'array',
            'exclusions' => 'array',
            'key_facts' => 'array',
            'promo' => 'array',
            'savings_hack' => 'array',
            'savings_table' => 'array',
            'savings_table_secondary' => 'array',
            'chooser' => 'array',
            'share_cta' => 'array',
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * The model lives under app/Domain/*, so the default factory-name guesser
     * looks in Database\Factories\Domain\… and misses. Point at the flat factory.
     *
     * @return OfferFactory
     */
    protected static function newFactory(): Factory
    {
        return OfferFactory::new();
    }

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * Whether this offer documents that the brand has no first-party discount.
     * Derived from `offer_type` — the single source of truth — so it can never
     * contradict a separately-stored flag. Filter advisory offers in queries via
     * the indexed `offer_type` column.
     */
    public function isAdvisoryNoDiscount(): bool
    {
        return $this->offer_type === OfferType::AdvisoryNoDiscount;
    }

    /**
     * Per-audience savings rows, in display order.
     *
     * @return HasMany<OfferTier, $this>
     */
    public function tiers(): HasMany
    {
        return $this->hasMany(OfferTier::class)->orderBy('sort_order');
    }

    /**
     * Numbered redemption steps (online + in-store), in display order.
     *
     * @return HasMany<RedemptionStep, $this>
     */
    public function redemptionSteps(): HasMany
    {
        return $this->hasMany(RedemptionStep::class)->orderBy('sort_order');
    }

    /**
     * Outbound affiliate links for this offer (hero CTA, sticky footer, source).
     *
     * @return HasMany<AffiliateLink, $this>
     */
    public function affiliateLinks(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }

    /**
     * The eligible cohorts this offer serves (drives filters + JSON-LD enumeration).
     *
     * @return BelongsToMany<Audience, $this>
     */
    public function audiences(): BelongsToMany
    {
        return $this->belongsToMany(Audience::class, 'offer_audience');
    }

    /**
     * Primary-source citations backing this offer's facts, in display order.
     *
     * @return MorphMany<Source, $this>
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(Source::class, 'sourceable')->orderBy('sort_order');
    }

    /**
     * Offer-scoped FAQs (bubble to the brand page's FAQPage schema), in order.
     *
     * @return MorphMany<Faq, $this>
     */
    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }
}
