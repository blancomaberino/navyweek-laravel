<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateBaseHubPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the Navy-bases hub pages (root, overseas, per-state, per-country).
 * See {@see GenerateBaseHubPagesAction}.
 */
final class GenerateBaseHubPagesCommand extends Command
{
    protected $signature = 'pages:generate-base-hubs';

    protected $description = 'Generate the Navy bases directory, overseas, state and country hub pages';

    public function handle(GenerateBaseHubPagesAction $action): int
    {
        $this->info("Generated {$action()} base hub pages.");

        return self::SUCCESS;
    }
}
