<?php

declare(strict_types=1);

namespace App\Domain\Research\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Who produced a research brief — a human editor or the headless
 * `military-discount-research` Claude pipeline dispatched from the CRM.
 */
enum ResearchedBy: string implements HasLabel
{
    case Human = 'human';
    case ClaudePipeline = 'claude-pipeline';

    public function label(): string
    {
        return match ($this) {
            self::Human => 'Human',
            self::ClaudePipeline => 'Claude pipeline',
        };
    }
}
