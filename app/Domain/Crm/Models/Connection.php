<?php

declare(strict_types=1);

namespace App\Domain\Crm\Models;

use App\Domain\Catalog\Models\AffiliateLink;
use App\Domain\Catalog\Models\AffiliateNetwork;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Enums\Audience;
use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Research\Models\Research;
use Database\Factories\ConnectionFactory;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * A brand in the CRM — the first of the four lifecycles the legacy flat
 * `Discount` record is split into (Connection → Offer → Page → Research).
 *
 * @property int $id
 * @property string $slug
 * @property string $brand
 * @property string $key
 * @property string|null $category
 * @property ConnectionStatus $status
 * @property int|null $priority_tier
 * @property bool $is_backlog
 * @property int|null $max_volume
 * @property int|null $total_volume
 * @property int|null $keyword_count
 * @property int|null $min_difficulty
 * @property string|null $cpc
 * @property string|null $top_keyword
 * @property Collection<int, Audience> $audiences
 * @property int $research_cadence_days
 * @property Carbon|null $last_verified_at
 * @property Carbon|null $next_review_due
 * @property int|null $duplicate_of
 * @property string|null $brand_home_url
 * @property string|null $official_url
 * @property string|null $logo_url
 * @property string|null $logo_background
 * @property int|null $default_affiliate_network_id
 * @property string|null $brief_path
 * @property string|null $source_csv
 * @property string|null $added_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Connection|null $duplicateOf
 * @property-read AffiliateNetwork|null $defaultAffiliateNetwork
 * @property-read Collection<int, ConnectionAlias> $aliases
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Offer> $offers
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Research> $research
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AffiliateLink> $affiliateLinks
 *
 * @method static \Database\Factories\ConnectionFactory factory($count = null, $state = [])
 */
class Connection extends Model
{
    /** @use HasFactory<ConnectionFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * Models live under app/Domain/*, so the default factory-name guesser looks
     * in Database\Factories\Domain\… and misses. Point at the flat factory. (A
     * central Factory::guessFactoryNamesUsing resolver can't return the required
     * class-string<Factory> without a suppression the phpstan-max gate forbids,
     * so each Domain model overrides newFactory() — the idiomatic Laravel way.)
     *
     * @return ConnectionFactory
     */
    protected static function newFactory(): Factory
    {
        return ConnectionFactory::new();
    }

    protected $fillable = [
        'slug',
        'brand',
        'key',
        'category',
        'status',
        'priority_tier',
        'is_backlog',
        'max_volume',
        'total_volume',
        'keyword_count',
        'min_difficulty',
        'cpc',
        'top_keyword',
        'audiences',
        'research_cadence_days',
        'last_verified_at',
        'next_review_due',
        'duplicate_of',
        'brand_home_url',
        'official_url',
        'logo_url',
        'logo_background',
        'default_affiliate_network_id',
        'brief_path',
        'source_csv',
        'added_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConnectionStatus::class,
            'priority_tier' => 'integer',
            'is_backlog' => 'boolean',
            'max_volume' => 'integer',
            'total_volume' => 'integer',
            'keyword_count' => 'integer',
            'min_difficulty' => 'integer',
            'cpc' => 'decimal:2',
            'audiences' => AsEnumCollection::of(Audience::class),
            'research_cadence_days' => 'integer',
            'last_verified_at' => 'date',
            'next_review_due' => 'date',
        ];
    }

    /**
     * The canonical connection this one duplicates (null when canonical itself).
     *
     * @return BelongsTo<Connection, $this>
     */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of');
    }

    /**
     * Keyword-variant slugs that resolve to this connection.
     *
     * @return HasMany<ConnectionAlias, $this>
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(ConnectionAlias::class);
    }

    /**
     * The discount offers this brand carries (everyday, promo, membership, …).
     *
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    /**
     * The versioned research briefs for this brand (newest = highest version).
     *
     * @return HasMany<Research, $this>
     */
    public function research(): HasMany
    {
        return $this->hasMany(Research::class);
    }

    /**
     * The brand's default affiliate network — an offer without its own network
     * falls back to this.
     *
     * @return BelongsTo<AffiliateNetwork, $this>
     */
    public function defaultAffiliateNetwork(): BelongsTo
    {
        return $this->belongsTo(AffiliateNetwork::class, 'default_affiliate_network_id');
    }

    /**
     * Outbound affiliate links attached at the brand level.
     *
     * @return HasMany<AffiliateLink, $this>
     */
    public function affiliateLinks(): HasMany
    {
        return $this->hasMany(AffiliateLink::class);
    }
}
