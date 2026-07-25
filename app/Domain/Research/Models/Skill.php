<?php

declare(strict_types=1);

namespace App\Domain\Research\Models;

use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * A research/QA skill in the provenance registry (e.g. `military-discount-research`,
 * `seo-geo`). `current_version` is bumped when `content_hash` changes; research
 * that used an older version becomes a re-verify trigger.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $current_version
 * @property string|null $content_hash
 * @property string|null $source_ref
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Research> $research
 *
 * @method static SkillFactory factory($count = null, $state = [])
 */
class Skill extends Model
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'current_version',
        'content_hash',
        'source_ref',
    ];

    /**
     * @return SkillFactory
     */
    protected static function newFactory(): Factory
    {
        return SkillFactory::new();
    }

    /**
     * Every research brief this skill contributed to.
     *
     * @return BelongsToMany<Research, $this>
     */
    public function research(): BelongsToMany
    {
        return $this->belongsToMany(Research::class, 'research_skill')
            ->withPivot(['skill_version', 'used_for'])
            ->withTimestamps();
    }
}
