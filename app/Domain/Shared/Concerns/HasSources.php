<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use App\Domain\Shared\Models\Source;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model the shared polymorphic citation relation (`sourceable` →
 * `sources`), ordered by `sort_order`. Extracted from the models that each
 * hand-copied this identical relation. The using model keeps its own
 * `@property-read ... $sources` docblock.
 *
 * @mixin Model
 *
 * @phpstan-require-extends Model
 */
trait HasSources
{
    /**
     * Primary-source citations for this model (shared polymorphic table), in display order.
     *
     * @return MorphMany<Source, $this>
     */
    public function sources(): MorphMany
    {
        return $this->morphMany(Source::class, 'sourceable')->orderBy('sort_order');
    }

    /**
     * Replace this model's sources with the given rows — deletes the existing
     * set, then recreates it. The idempotency contract (delete-then-recreate, so
     * a re-import never duplicates) lives here once, for every Stage-B importer.
     *
     * @param  iterable<int, array<string, mixed>>  $rows
     */
    public function replaceSources(iterable $rows): void
    {
        $this->sources()->delete();
        $this->sources()->createMany($rows);
    }
}
