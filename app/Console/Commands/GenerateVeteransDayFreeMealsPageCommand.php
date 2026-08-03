<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Pages\GenerateVeteransDayFreeMealsPageAction;
use Illuminate\Console\Command;

/**
 * Seeds the `/veterans-day/free-meals/` roundup page (routing/SEO row only — the
 * offers table + ItemList/FAQ render live from the veterans_day_meals aggregate).
 */
final class GenerateVeteransDayFreeMealsPageCommand extends Command
{
    protected $signature = 'pages:generate-veterans-day-free-meals';

    protected $description = 'Seed the /veterans-day/free-meals/ roundup page.';

    public function handle(GenerateVeteransDayFreeMealsPageAction $action): int
    {
        $action();

        $this->info('Seeded the /veterans-day/free-meals/ roundup page.');

        return self::SUCCESS;
    }
}
