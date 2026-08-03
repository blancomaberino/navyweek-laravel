<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateYmylGuidePagesAction;
use Illuminate\Console\Command;

/**
 * Seeds the YMYL guide content pages (`/va-disability/`, `/veterans-home-care/`) with
 * their initial editor-managed body. Idempotent — never clobbers an editor's body.
 */
final class GenerateYmylGuidePagesCommand extends Command
{
    protected $signature = 'pages:generate-ymyl-guides';

    protected $description = 'Seed the VA-disability and veterans-home-care guide pages.';

    public function handle(GenerateYmylGuidePagesAction $action): int
    {
        $count = $action();

        $this->info("Seeded {$count} YMYL guide page(s).");

        return self::SUCCESS;
    }
}
