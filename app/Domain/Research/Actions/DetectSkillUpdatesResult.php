<?php

declare(strict_types=1);

namespace App\Domain\Research\Actions;

/**
 * Summary of one `DetectSkillUpdatesAction` run — what the detector did, for the
 * command's report and for assertions in tests.
 *
 * @param  list<string>  $bumped  skill keys whose content changed → version bumped
 * @param  list<string>  $baselined  skill keys hashed for the first time (no bump)
 * @param  list<string>  $missing  skill keys with no files on disk (nothing done)
 */
final readonly class DetectSkillUpdatesResult
{
    /**
     * @param  list<string>  $bumped
     * @param  list<string>  $baselined
     * @param  list<string>  $missing
     */
    public function __construct(
        public array $bumped,
        public array $baselined,
        public array $missing,
        public int $connectionsFlagged,
    ) {}
}
