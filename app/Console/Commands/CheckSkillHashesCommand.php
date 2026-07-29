<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Research\Models\Skill;
use App\Domain\Research\Services\SkillHasher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Reports skill-provenance drift: compares each registered skill's on-disk content
 * (SKILL.md + references, under `config('research.skills_path')`) to its stored
 * `content_hash`. READ-ONLY — surfaces changed / missing / unhashed skills so the
 * research that cited an older version can be re-verified. `--check` exits non-zero
 * on drift for CI/scheduling. Bumping the version + flagging affected research is a
 * deliberate follow-up (it mutates the pipeline); this only detects.
 */
final class CheckSkillHashesCommand extends Command
{
    protected $signature = 'skills:check-hashes {--check : Exit non-zero on drift (report only, never writes)}';

    protected $description = 'Report skills whose on-disk content no longer matches their stored content_hash.';

    public function handle(SkillHasher $hasher): int
    {
        $base = rtrim(Config::string('research.skills_path'), '/');
        $issues = 0;

        foreach (Skill::query()->orderBy('key')->get() as $skill) {
            $current = $hasher->hash("{$base}/{$skill->key}");

            if ($current === null) {
                $this->warn("✗ {$skill->key}: skill files not found under {$base}");
                $issues++;

                continue;
            }

            if ($skill->content_hash === null || $skill->content_hash === '') {
                $this->warn("✗ {$skill->key}: no stored content_hash (never hashed)");
                $issues++;

                continue;
            }

            if ($current !== $skill->content_hash) {
                $this->warn("✗ {$skill->key}: content changed since last hash");
                $issues++;

                continue;
            }

            $this->info("✓ {$skill->key}: up to date");
        }

        if ($issues === 0) {
            $this->info('All skills match their stored content hash.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn("{$issues} skill(s) drifted from their stored hash.");

        return $this->option('check') ? self::FAILURE : self::SUCCESS;
    }
}
