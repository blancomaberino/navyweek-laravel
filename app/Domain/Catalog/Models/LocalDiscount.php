<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\LocalVerification;
use App\Domain\Pillars\Models\UsState;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\LocalDiscountFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A local-business discount page (port of `localDiscounts/*.ts`) — a distinct
 * page type from the national brand guides, keyed geographically at
 * `/discounts/<state>/<city>/<business_slug>/`. Its physical storefronts are the
 * `local_stores` children (each with its own `local_store_hours`); `state` is a
 * soft slug FK to the shared `us_states` lookup. The military `audience` is the
 * legacy fixed 5-flag struct (booleans). Cohesive display lists are JSON; FAQs
 * and sources hang off the shared polymorphic tables.
 *
 * @property int $id
 * @property string $state
 * @property string $state_name
 * @property string $state_abbr
 * @property string $city
 * @property string $city_name
 * @property string $business_slug
 * @property string $company
 * @property string $business_type
 * @property string $category
 * @property string|null $logo
 * @property string|null $logo_alt
 * @property string|null $logo_background
 * @property string $official_url
 * @property string $brand_home_url
 * @property string $headline_discount
 * @property string $discount_summary
 * @property LocalVerification $verification
 * @property string|null $verification_detail
 * @property bool $active_duty
 * @property bool $veterans
 * @property bool $retirees
 * @property bool $reserve_guard
 * @property bool $military_family
 * @property array<int, string> $eligibility
 * @property array<int, array{audience: string, amount: string, note?: string}> $tiers
 * @property array<int, array{title: string, detail: string}> $redeem_in_store
 * @property array<int, string> $exclusions
 * @property array<int, array<string, mixed>> $nearby_bases
 * @property string|null $service_area
 * @property string|null $price_range
 * @property array<int, string> $intro
 * @property array<int, string> $details
 * @property array<int, array{label: string, value: string}> $key_facts
 * @property string $meta_title
 * @property string $meta_description
 * @property string $h1
 * @property string $hero_tagline
 * @property string $primary_keyword
 * @property string|null $og_image
 * @property Carbon $date_published
 * @property Carbon $date_modified
 * @property string $last_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UsState|null $usState
 * @property-read Collection<int, LocalStore> $stores
 * @property-read Collection<int, Source> $sources
 * @property-read Collection<int, Faq> $faqs
 *
 * @method static LocalDiscountFactory factory($count = null, $state = [])
 */
class LocalDiscount extends Model
{
    /** @use HasFactory<LocalDiscountFactory> */
    use HasFactory;

    protected $fillable = [
        'state',
        'state_name',
        'state_abbr',
        'city',
        'city_name',
        'business_slug',
        'company',
        'business_type',
        'category',
        'logo',
        'logo_alt',
        'logo_background',
        'official_url',
        'brand_home_url',
        'headline_discount',
        'discount_summary',
        'verification',
        'verification_detail',
        'active_duty',
        'veterans',
        'retirees',
        'reserve_guard',
        'military_family',
        'eligibility',
        'tiers',
        'redeem_in_store',
        'exclusions',
        'nearby_bases',
        'service_area',
        'price_range',
        'intro',
        'details',
        'key_facts',
        'meta_title',
        'meta_description',
        'h1',
        'hero_tagline',
        'primary_keyword',
        'og_image',
        'date_published',
        'date_modified',
        'last_verified',
    ];

    protected function casts(): array
    {
        return [
            'verification' => LocalVerification::class,
            'active_duty' => 'boolean',
            'veterans' => 'boolean',
            'retirees' => 'boolean',
            'reserve_guard' => 'boolean',
            'military_family' => 'boolean',
            'eligibility' => 'array',
            'tiers' => 'array',
            'redeem_in_store' => 'array',
            'exclusions' => 'array',
            'nearby_bases' => 'array',
            'intro' => 'array',
            'details' => 'array',
            'key_facts' => 'array',
            'date_published' => 'date',
            'date_modified' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return LocalDiscountFactory
     */
    protected static function newFactory(): Factory
    {
        return LocalDiscountFactory::new();
    }

    /**
     * Whether any military audience flag is set (port of the legacy
     * `hasAnyLocalAudience`). Derived from the five booleans.
     */
    public function hasAnyAudience(): bool
    {
        return $this->active_duty
            || $this->veterans
            || $this->retirees
            || $this->reserve_guard
            || $this->military_family;
    }

    /**
     * The U.S. state this page sits in, joined by slug against the shared lookup.
     *
     * @return BelongsTo<UsState, $this>
     */
    public function usState(): BelongsTo
    {
        return $this->belongsTo(UsState::class, 'state', 'slug');
    }

    /**
     * The physical storefronts, in display order (first = primary NAP + schema).
     *
     * @return HasMany<LocalStore, $this>
     */
    public function stores(): HasMany
    {
        return $this->hasMany(LocalStore::class)->orderBy('sort_order');
    }

    /**
     * Primary-source citations backing this page's facts (shared table), in order.
     *
     * @return MorphMany<Source, $this>
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(Source::class, 'sourceable')->orderBy('sort_order');
    }

    /**
     * Page-scoped FAQs (shared polymorphic table), in display order.
     *
     * @return MorphMany<Faq, $this>
     */
    public function faqs(): MorphMany
    {
        return $this->morphMany(Faq::class, 'faqable')->orderBy('sort_order');
    }
}
