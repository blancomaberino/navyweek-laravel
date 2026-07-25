<?php

declare(strict_types=1);

namespace App\Domain\Research\Models;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Enums\ResearchedBy;
use App\Domain\Research\Enums\ResearchStatus;
use App\Domain\Shared\Enums\ConfidenceLevel;
use App\Domain\Shared\Models\Source;
use Database\Factories\ResearchFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A sourced, versioned research brief — the durable truth a discount page derives
 * from. Fourth of the four lifecycles (Connection → Offer → Page → Research).
 *
 * @property int $id
 * @property int $connection_id
 * @property int|null $offer_id
 * @property string|null $brief_path
 * @property string $raw_markdown
 * @property string|null $executive_summary
 * @property array<array-key, mixed>|null $verified_facts
 * @property array<array-key, mixed>|null $decision_table
 * @property array<array-key, mixed>|null $maintenance
 * @property array<array-key, mixed>|null $recommended_copy
 * @property ConfidenceLevel|null $confidence_overall
 * @property Carbon|null $last_verified
 * @property ResearchedBy $researched_by
 * @property string|null $skill_key
 * @property string|null $skill_version
 * @property ResearchStatus $status
 * @property int $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Connection $connection
 * @property-read Offer|null $offer
 * @property-read Collection<int, Skill> $skills
 * @property-read Collection<int, Source> $sources
 *
 * @method static ResearchFactory factory($count = null, $state = [])
 */
class Research extends Model
{
    /** @use HasFactory<ResearchFactory> */
    use HasFactory;

    /** "research" is both singular and plural — pin the table name. */
    protected $table = 'research';

    protected $fillable = [
        'connection_id',
        'offer_id',
        'brief_path',
        'raw_markdown',
        'executive_summary',
        'verified_facts',
        'decision_table',
        'maintenance',
        'recommended_copy',
        'confidence_overall',
        'last_verified',
        'researched_by',
        'skill_key',
        'skill_version',
        'status',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'verified_facts' => 'array',
            'decision_table' => 'array',
            'maintenance' => 'array',
            'recommended_copy' => 'array',
            'confidence_overall' => ConfidenceLevel::class,
            'last_verified' => 'date',
            'researched_by' => ResearchedBy::class,
            'status' => ResearchStatus::class,
            'version' => 'integer',
        ];
    }

    /**
     * @return ResearchFactory
     */
    protected static function newFactory(): Factory
    {
        return ResearchFactory::new();
    }

    /**
     * @return BelongsTo<Connection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(Connection::class);
    }

    /**
     * @return BelongsTo<Offer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Every skill (with the version + role) that contributed to this brief.
     *
     * @return BelongsToMany<Skill, $this>
     */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'research_skill')
            ->withPivot(['skill_version', 'used_for'])
            ->withTimestamps();
    }

    /**
     * Primary-source citations this brief verified its facts against, in order.
     *
     * @return MorphMany<Source, $this>
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(Source::class, 'sourceable')->orderBy('sort_order');
    }
}
