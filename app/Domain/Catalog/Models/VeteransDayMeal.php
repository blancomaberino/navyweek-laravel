<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\MealEligibility;
use App\Domain\Catalog\Enums\MealRedemption;
use App\Domain\Catalog\Enums\MealStatus;
use App\Domain\Crm\Models\Connection;
use Database\Factories\VeteransDayMealFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * One brand's Veterans Day free-meal offer (port of `veterans-day-meals/*.ts`),
 * a seasonal roundup spoke under `/veterans-day/`. YMYL render gate: an offer is
 * shown ONLY when `status = Verified` AND it carries a primary `source_url` — see
 * VeteransDayMealRepository::verified() and the model's `isRenderable()`. Facts
 * come only from that source. `discount_slug` is a soft FK to a Connection slug
 * (the brand's `/discount/` guide); when unset or unresolved the cell has no
 * internal link (a backlog item, not an error).
 *
 * @property int $id
 * @property string $slug
 * @property string $brand
 * @property string|null $discount_slug
 * @property string $offer
 * @property Collection<int, MealEligibility> $eligibility
 * @property bool $dependents_eligible
 * @property MealRedemption $redemption
 * @property string $proof_required
 * @property string $offer_date
 * @property bool $nationwide
 * @property string $source_url
 * @property string $source_label
 * @property Carbon $last_verified_at
 * @property MealStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connection|null $discount
 *
 * @method static VeteransDayMealFactory factory($count = null, $state = [])
 */
class VeteransDayMeal extends Model
{
    /** @use HasFactory<VeteransDayMealFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'brand',
        'discount_slug',
        'offer',
        'eligibility',
        'dependents_eligible',
        'redemption',
        'proof_required',
        'offer_date',
        'nationwide',
        'source_url',
        'source_label',
        'last_verified_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'eligibility' => AsEnumCollection::of(MealEligibility::class),
            'redemption' => MealRedemption::class,
            'status' => MealStatus::class,
            'dependents_eligible' => 'boolean',
            'nationwide' => 'boolean',
            'last_verified_at' => 'date',
        ];
    }

    /**
     * Domain-namespaced models miss the default factory guesser; point at the
     * flat factory explicitly.
     *
     * @return VeteransDayMealFactory
     */
    protected static function newFactory(): Factory
    {
        return VeteransDayMealFactory::new();
    }

    /**
     * The YMYL render gate, verbatim from the legacy roundup: an offer shows only
     * when it is verified AND carries a primary source URL. Single source of truth
     * for both the model and the repository's `verified()` read.
     */
    public function isRenderable(): bool
    {
        return $this->status === MealStatus::Verified && $this->source_url !== '';
    }

    /**
     * The brand's national `/discount/` guide, joined by slug. Null when the brand
     * has no Discount page yet (a backlog item, not an error).
     *
     * @return BelongsTo<Connection, $this>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Connection::class, 'discount_slug', 'slug');
    }

    /** Who-qualifies cell: the eligibility labels joined, e.g. "Veterans, Active duty". */
    public function eligibilityLabelList(): string
    {
        return $this->eligibility
            ->map(static fn (MealEligibility $e): string => $e->label())
            ->implode(', ');
    }

    /** The green "Verified <Mon YYYY>" badge text, from `last_verified_at` (UTC), e.g. "Verified Jun 2026". */
    public function verifiedBadge(): string
    {
        return 'Verified '.$this->last_verified_at->format('M Y');
    }

    /**
     * The "When" cell: an ISO `Y-m-d` offer_date renders as "Nov 11, 2026"; any other
     * string (a human date range) passes through verbatim, matching the legacy.
     */
    public function whenLabel(): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->offer_date) === 1) {
            return Carbon::parse($this->offer_date)->format('M j, Y');
        }

        return $this->offer_date;
    }
}
