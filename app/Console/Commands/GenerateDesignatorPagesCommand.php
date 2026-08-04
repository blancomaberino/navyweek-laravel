<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Pillars\Pages\GenerateDesignatorPagesAction;
use Illuminate\Console\Command;

/**
 * Generates the officer-designator hub, community hubs and detail pages.
 * See {@see GenerateDesignatorPagesAction}.
 */
final class GenerateDesignatorPagesCommand extends Command
{
    protected $signature = 'pages:generate-designators';

    protected $description = 'Generate the Navy officer designator hub, community hubs and detail pages';

    public function handle(GenerateDesignatorPagesAction $action): int
    {
        $this->info("Generated {$action()} designator pages.");

        return self::SUCCESS;
    }
}
