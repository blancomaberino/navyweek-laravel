<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Feed;

/**
 * The rendered output of one {@see FeedGenerator} run: the two files to write. Pure
 * data — the generator never touches the filesystem, so the command owns the writes.
 */
final readonly class FeedResult
{
    public function __construct(
        public string $json,     // data/navy-week-2026.json
        public string $llmsTxt,  // llms.txt
    ) {}
}
