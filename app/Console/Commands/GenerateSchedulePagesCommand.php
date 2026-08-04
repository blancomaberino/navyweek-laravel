<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateSchedulePagesAction;
use Illuminate\Console\Command;

/** Generates /schedule/ and /map/. See {@see GenerateSchedulePagesAction}. */
final class GenerateSchedulePagesCommand extends Command
{
    protected $signature = 'pages:generate-schedule';

    protected $description = 'Generate the Navy Week schedule and route map pages';

    public function handle(GenerateSchedulePagesAction $action): int
    {
        $this->info("Generated {$action()} schedule pages.");

        return self::SUCCESS;
    }
}
