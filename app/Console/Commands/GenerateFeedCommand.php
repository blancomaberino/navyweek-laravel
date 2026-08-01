<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publishing\Feed\FeedGenerator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Regenerates the machine-readable LLM/citability resources — `public/llms.txt` and
 * `public/data/navy-week-2026.json` — from the live aggregates (hand-port of the parent
 * repo's `scripts/generate-llm-feed.mjs`). The sitemap's `data` bucket
 * (`sitemap:generate`) lists both files once they exist.
 */
final class GenerateFeedCommand extends Command
{
    protected $signature = 'feed:generate';

    protected $description = 'Regenerate llms.txt + the Navy Week JSON feed from the published aggregates.';

    public function handle(FeedGenerator $generator): int
    {
        $result = $generator->build();

        File::ensureDirectoryExists(public_path('data'));
        File::put(public_path('data/navy-week-2026.json'), $result->json);
        File::put(public_path('llms.txt'), $result->llmsTxt);

        $this->info('Wrote public/data/navy-week-2026.json ('.strlen($result->json).' bytes) and public/llms.txt ('.strlen($result->llmsTxt).' bytes).');

        return self::SUCCESS;
    }
}
