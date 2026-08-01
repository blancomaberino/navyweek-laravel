<?php

declare(strict_types=1);

namespace App\Domain\Research\Jobs;

use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Research\Actions\LaunchResearchAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued wrapper (Horizon) around {@see LaunchResearchAction} — the CRM's "Launch
 * research" action + the cadence auto-dispatch enqueue this.
 *
 * Carries only the connection id (resolved through the repository in `handle`, never a
 * serialized model). A headless run is expensive and spawns an external process, so it
 * does NOT auto-retry (`tries = 1`): a failure lands in `failed_jobs` for a human to
 * inspect the Draft + re-launch, rather than silently respawning the CLI.
 */
final class LaunchResearchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly int $connectionId) {}

    public function handle(LaunchResearchAction $launch, ConnectionRepositoryInterface $connections): void
    {
        $connection = $connections->findById($this->connectionId);
        if ($connection === null) {
            return; // connection deleted before the job ran — nothing to research
        }

        $launch($connection);
    }
}
