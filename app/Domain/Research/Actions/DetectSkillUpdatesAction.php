<?php

declare(strict_types=1);

namespace App\Domain\Research\Actions;

use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;
use App\Domain\Research\Repositories\SkillRepositoryInterface;
use App\Domain\Research\Services\SkillHasher;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * The WRITE half of skill-provenance drift detection. Where `skills:check-hashes`
 * only reports, this re-hashes each registered skill's on-disk content (SKILL.md +
 * references, under `config('research.skills_path')`) and, when it changed:
 *
 *  1. bumps the skill's `current_version` and stores the new `content_hash`, then
 *  2. flags every Connection whose LATEST brief cited that skill at the now-superseded
 *     version as `needs-reverify`, so the CRM surfaces pages built on stale guidance.
 *
 * A skill hashed for the FIRST time (no stored `content_hash`) only records the
 * baseline — there's no earlier provenance to invalidate, so it neither bumps nor
 * flags. A skill with no files on disk is reported and skipped (never bumped).
 *
 * The version bump runs in its own short transaction, then each connection is flagged
 * in an isolated transaction (mirroring FlagStaleResearchCommand): one bad row can't
 * abort the run, and — because the bump already committed — a partial failure won't
 * force the change to be re-detected and the version re-bumped on the next run. All
 * writes go through the repositories (each locks its row).
 */
final class DetectSkillUpdatesAction
{
    public function __construct(
        private readonly SkillHasher $hasher,
        private readonly SkillRepositoryInterface $skills,
        private readonly ResearchRepositoryInterface $research,
        private readonly ConnectionRepositoryInterface $connections,
    ) {}

    public function execute(): DetectSkillUpdatesResult
    {
        $base = rtrim(Config::string('research.skills_path'), '/');

        $bumped = [];
        $baselined = [];
        $missing = [];
        $connectionsFlagged = 0;

        foreach ($this->skills->all() as $skill) {
            $hash = $this->hasher->hash("{$base}/{$skill->key}");

            if ($hash === null) {
                $missing[] = $skill->key;

                continue;
            }

            if ($hash === $skill->content_hash) {
                continue; // unchanged since last hash
            }

            $isBaseline = $skill->content_hash === null || $skill->content_hash === '';

            // Record the hash (and bump the version on a real change) in its own short
            // transaction so the commit stands even if a later connection flag fails.
            $updated = DB::transaction(fn () => $this->skills->recordContentHash($skill, $hash, ! $isBaseline));

            if ($isBaseline) {
                $baselined[] = $skill->key;

                continue;
            }

            $bumped[] = $skill->key;

            // Flag connections whose latest brief cited the pre-bump version. Each in its
            // own transaction (markNeedsReverify re-checks the active state under the lock
            // and no-ops a connection that's since left the active set).
            foreach ($this->research->connectionIdsWithStaleSkillProvenance($updated->id, $updated->current_version) as $connectionId) {
                try {
                    $didFlag = DB::transaction(function () use ($connectionId): bool {
                        $connection = $this->connections->lockById($connectionId);

                        return $connection !== null && $this->connections->markNeedsReverify($connection);
                    });

                    if ($didFlag) {
                        $connectionsFlagged++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }

        return new DetectSkillUpdatesResult($bumped, $baselined, $missing, $connectionsFlagged);
    }
}
