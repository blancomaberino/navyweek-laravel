<?php

declare(strict_types=1);

namespace App\Domain\Research\Actions;

use App\Domain\Crm\Models\Connection;
use App\Domain\Research\Exceptions\ResearchAutomationDisabledException;
use App\Domain\Research\Jobs\LaunchResearchJob;
use App\Domain\Research\Models\Research;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;
use App\Domain\Research\Repositories\SkillRepositoryInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Launches a headless research run for a connection — "launch research from the CRM",
 * encapsulated as one action (dispatched as {@see LaunchResearchJob}).
 *
 * It opens a Draft `research` row at the next version, stamped with the current
 * `skill_key`/`skill_version` of every skill the run will use (provenance is captured
 * up-front, so it survives even if the process fails), then runs the
 * `military-discount-research` skill via the Claude CLI and stores the returned brief
 * verbatim in `raw_markdown`. Parsing that brief into the structured columns +
 * dispatching `ResearchCompleted` is a deliberate follow-up (see the architecture doc).
 *
 * Security: the run spawns `claude … --dangerously-skip-permissions`, so it is gated
 * behind `research.automation.enabled` (OFF by default — merging never enables live
 * spawning) and only ever runs on a controlled host that opts in. The command is built
 * as an ARRAY of argv entries (never a shell string), so the brand/slug can't inject.
 */
final class LaunchResearchAction
{
    public function __construct(
        private readonly SkillRepositoryInterface $skills,
        private readonly ResearchRepositoryInterface $research,
    ) {}

    public function __invoke(Connection $connection): Research
    {
        if (! Config::boolean('research.automation.enabled')) {
            throw ResearchAutomationDisabledException::make();
        }

        // Capture skill provenance before spawning anything.
        $run = $this->research->createDraftRun($connection->id, $this->resolveSkills());

        $result = Process::path(Config::string('research.automation.working_directory'))
            ->timeout(Config::integer('research.automation.timeout'))
            ->run($this->command($connection));

        if (! $result->successful()) {
            // The Draft (with its provenance) stays for inspection/retry; surface the
            // failure so the job records it in failed_jobs.
            throw new RuntimeException(
                "Headless research for connection {$connection->id} exited with code {$result->exitCode()}."
            );
        }

        $this->research->storeRawOutput($run, $result->output());

        return $run;
    }

    /**
     * Resolve the configured skills to their registered ids + current versions, primary
     * first. The primary (first configured) MUST be registered — its version is stamped
     * on the row; an unregistered secondary is skipped (it just won't appear in the
     * provenance pivot).
     *
     * @return non-empty-list<array{id: int, key: string, version: string, used_for: string}>
     */
    private function resolveSkills(): array
    {
        /** @var array<string, string> $configured */
        $configured = Config::array('research.automation.skills');
        $primaryKey = (string) array_key_first($configured);

        $resolved = [];
        foreach ($configured as $key => $usedFor) {
            $skill = $this->skills->findByKey((string) $key);
            if ($skill === null) {
                if ((string) $key === $primaryKey) {
                    throw new RuntimeException("Primary research skill '{$key}' is not registered; cannot stamp provenance.");
                }

                continue;
            }
            $resolved[] = [
                'id' => $skill->id,
                'key' => $skill->key,
                'version' => $skill->current_version,
                'used_for' => (string) $usedFor,
            ];
        }

        if ($resolved === []) {
            throw new RuntimeException('No configured research skills are registered; cannot launch a run.');
        }

        return $resolved;
    }

    /**
     * The CLI invocation as argv entries — NOT a shell string. The brand/slug are
     * separate arguments, so no value can break out into shell interpolation.
     *
     * @return list<string>
     */
    private function command(Connection $connection): array
    {
        return [
            Config::string('research.automation.binary'),
            '-p',
            $this->prompt($connection),
            '--dangerously-skip-permissions',
        ];
    }

    private function prompt(Connection $connection): string
    {
        return "Run the military-discount-research skill for the brand \"{$connection->brand}\" "
            ."(slug: {$connection->slug}). Follow the skill's runbook and output the completed brief as Markdown.";
    }
}
