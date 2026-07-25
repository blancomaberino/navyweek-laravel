<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Models;

use Database\Factories\UsStateFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A U.S. state (or DC) — the lookup the state-based base hubs group on
 * (`/bases/<state>/`). Port of `bases/states.ts`.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $abbr
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Base> $bases
 *
 * @method static UsStateFactory factory($count = null, $state = [])
 */
class UsState extends Model
{
    /** @use HasFactory<UsStateFactory> */
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'abbr',
    ];

    /**
     * Domain-namespaced models miss the default factory guesser (it looks under
     * Database\Factories\Domain\…); point at the flat factory explicitly.
     *
     * @return UsStateFactory
     */
    protected static function newFactory(): Factory
    {
        return UsStateFactory::new();
    }

    /**
     * Bases located in this state (joined on the `state` slug).
     *
     * @return HasMany<Base, $this>
     */
    public function bases(): HasMany
    {
        return $this->hasMany(Base::class, 'state', 'slug');
    }
}
