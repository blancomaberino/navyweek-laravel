<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Research\Actions\DetectSkillUpdatesAction;
use Illuminate\Console\Command;

/**
 * The WRITE counterpart to `skills:check-hashes`: re-hashes each skill and, on a real
 * content change, bumps `current_version` + flags connections whose latest brief used
 * the superseded version as `needs-reverify`. Thin wrapper over
 * DetectSkillUpdatesAction; scheduled after the daily research run so a mid-cadence
 * skill edit surfaces the affected pages for re-verification.
 */
final class DetectSkillUpdatesCommand extends Command
{
    protected $signature = 'skills:detect-updates';

    protected $description = 'Bump changed skills and flag connections whose latest research used a superseded skill version.';

    public function handle(DetectSkillUpdatesAction $action): int
    {
        $result = $action->execute();

        foreach ($result->bumped as $key) {
            $this->info("↑ {$key}: content changed → version bumped");
        }
        foreach ($result->baselined as $key) {
            $this->line("• {$key}: first hash recorded (baseline)");
        }
        foreach ($result->missing as $key) {
            $this->warn("✗ {$key}: skill files not found — skipped");
        }

        $this->newLine();
        $this->info(sprintf(
            '%d skill(s) bumped, %d baselined, %d missing; %d connection(s) flagged for re-verification.',
            count($result->bumped),
            count($result->baselined),
            count($result->missing),
            $result->connectionsFlagged,
        ));

        return self::SUCCESS;
    }
}
