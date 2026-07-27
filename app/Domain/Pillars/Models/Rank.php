<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use App\Domain\Pillars\Enums\DesignatorCommunity;
use App\Domain\Pillars\Enums\HistoricRatingEra;
use App\Domain\Pillars\Enums\RankCategory;
use App\Domain\Pillars\Enums\RatingCommunity;
use App\Domain\Shared\Concerns\HasFaqs;
use App\Domain\Shared\Concerns\HasSources;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Factories\RankFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;

/**
 * A Navy rank / paygrade / designator / rating — the second reference pillar,
 * modeled as single-table inheritance over the `category` discriminator (port of
 * the legacy `NavyRankEntry` union). Common columns apply to every row; the variant
 * groups are nullable and populated only for their category. FAQs and sources hang
 * off the shared polymorphic tables; nested arrays are JSON.
 *
 * @property int $id
 * @property string $slug
 * @property RankCategory $category
 * @property string $name
 * @property string $abbreviation
 * @property string $paygrade
 * @property string $insignia_path
 * @property string $insignia_alt
 * @property string $meta_title
 * @property string $meta_description
 * @property string $h1
 * @property string $hero_tagline
 * @property string $overview
 * @property string $history
 * @property array<int, string> $responsibilities
 * @property string $addressing
 * @property array<int, string> $prerequisites
 * @property array<int, string> $common_assignments
 * @property array<string, mixed>|null $pay_range
 * @property string|null $related_base_slug
 * @property string|null $related_base_note
 * @property Carbon $last_updated
 * @property string|null $nato_code
 * @property string|null $next_slug
 * @property string|null $previous_slug
 * @property bool|null $is_flag_officer
 * @property bool|null $is_chief
 * @property array<int, array<string, mixed>>|null $community_variants
 * @property array<int, array<string, mixed>>|null $special_billets
 * @property string|null $designator_code
 * @property DesignatorCommunity|null $designator_community
 * @property array<int, string>|null $commissioning_sources
 * @property array<int, string>|null $related_designators
 * @property string|null $device_description
 * @property RatingCommunity|null $rating_community
 * @property string|null $what_they_do
 * @property string|null $asvab_requirement
 * @property int|null $asvab_score_min
 * @property string|null $a_school_location
 * @property string|null $a_school_location_slug
 * @property string|null $a_school_duration
 * @property string|null $clearance_required
 * @property int|null $enlistment_obligation_years
 * @property array<int, string>|null $typical_platforms
 * @property array<int, array<string, mixed>>|null $career_path
 * @property array<int, string>|null $related_ratings
 * @property array<int, string>|null $nec_examples
 * @property string|null $badge_description
 * @property array<int, string>|null $predecessor_ratings
 * @property array<int, string>|null $related_base_slugs
 * @property array<int, array<string, mixed>>|null $training_pipeline
 * @property string|null $active_period
 * @property string|null $years_active
 * @property int|null $decommissioned_year
 * @property string|null $decommission_reason
 * @property array<int, string>|null $successor_ratings
 * @property array<int, string>|null $notable_for
 * @property SupportCollection<int, HistoricRatingEra>|null $era_tags
 * @property string|null $merged_into_slug
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Rank|null $nextRank
 * @property-read Rank|null $previousRank
 * @property-read Rank|null $mergedIntoRank
 * @property-read Base|null $relatedBase
 * @property-read Base|null $aSchoolBase
 * @property-read Collection<int, Faq> $faqs
 * @property-read Collection<int, Source> $sources
 *
 * @method static RankFactory factory($count = null, $state = [])
 */
class Rank extends Model
{
    /** @use HasFactory<RankFactory> */
    use HasFactory;

    use HasFaqs;
    use HasSources;

    protected $fillable = [
        'slug',
        'category',
        'name',
        'abbreviation',
        'paygrade',
        'insignia_path',
        'insignia_alt',
        'meta_title',
        'meta_description',
        'h1',
        'hero_tagline',
        'overview',
        'history',
        'responsibilities',
        'addressing',
        'prerequisites',
        'common_assignments',
        'pay_range',
        'related_base_slug',
        'related_base_note',
        'last_updated',
        'nato_code',
        'next_slug',
        'previous_slug',
        'is_flag_officer',
        'is_chief',
        'community_variants',
        'special_billets',
        'designator_code',
        'designator_community',
        'commissioning_sources',
        'related_designators',
        'device_description',
        'rating_community',
        'what_they_do',
        'asvab_requirement',
        'asvab_score_min',
        'a_school_location',
        'a_school_location_slug',
        'a_school_duration',
        'clearance_required',
        'enlistment_obligation_years',
        'typical_platforms',
        'career_path',
        'related_ratings',
        'nec_examples',
        'badge_description',
        'predecessor_ratings',
        'related_base_slugs',
        'training_pipeline',
        'active_period',
        'years_active',
        'decommissioned_year',
        'decommission_reason',
        'successor_ratings',
        'notable_for',
        'era_tags',
        'merged_into_slug',
    ];

    protected function casts(): array
    {
        return [
            'category' => RankCategory::class,
            'designator_community' => DesignatorCommunity::class,
            'rating_community' => RatingCommunity::class,
            'era_tags' => AsEnumCollection::of(HistoricRatingEra::class),
            'responsibilities' => 'array',
            'prerequisites' => 'array',
            'common_assignments' => 'array',
            'pay_range' => 'array',
            'community_variants' => 'array',
            'special_billets' => 'array',
            'commissioning_sources' => 'array',
            'related_designators' => 'array',
            'typical_platforms' => 'array',
            'career_path' => 'array',
            'related_ratings' => 'array',
            'nec_examples' => 'array',
            'predecessor_ratings' => 'array',
            'related_base_slugs' => 'array',
            'training_pipeline' => 'array',
            'successor_ratings' => 'array',
            'notable_for' => 'array',
            'is_flag_officer' => 'boolean',
            'is_chief' => 'boolean',
            'asvab_score_min' => 'integer',
            'enlistment_obligation_years' => 'integer',
            'decommissioned_year' => 'integer',
            'last_updated' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser (it looks under
     * Database\Factories\Domain\…); point at the flat factory explicitly.
     *
     * @return RankFactory
     */
    protected static function newFactory(): Factory
    {
        return RankFactory::new();
    }

    /** True for the two rating categories. Delegates to the discriminator. */
    public function isRating(): bool
    {
        return $this->category->isRating();
    }

    /**
     * Next entry in the category's sequence (officers/enlisted), joined by slug.
     *
     * @return BelongsTo<Rank, $this>
     */
    public function nextRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'next_slug', 'slug');
    }

    /**
     * Previous entry in the category's sequence, joined by slug.
     *
     * @return BelongsTo<Rank, $this>
     */
    public function previousRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'previous_slug', 'slug');
    }

    /**
     * The active rating a historical rating was merged into, joined by slug.
     *
     * @return BelongsTo<Rank, $this>
     */
    public function mergedIntoRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'merged_into_slug', 'slug');
    }

    /**
     * The entry's primary related base, joined by slug.
     *
     * @return BelongsTo<Base, $this>
     */
    public function relatedBase(): BelongsTo
    {
        return $this->belongsTo(Base::class, 'related_base_slug', 'slug');
    }

    /**
     * The base hosting this rating's "A" school, joined by slug (ratings only).
     *
     * @return BelongsTo<Base, $this>
     */
    public function aSchoolBase(): BelongsTo
    {
        return $this->belongsTo(Base::class, 'a_school_location_slug', 'slug');
    }
}
