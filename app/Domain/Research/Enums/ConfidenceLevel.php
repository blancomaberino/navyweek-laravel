<?php

declare(strict_types=1);

namespace App\Domain\Research\Enums;

/**
 * Overall confidence in a brief's verified facts (mirrors the per-fact
 * High/Medium/Low column in the legacy research briefs).
 */
enum ConfidenceLevel: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'High',
            self::Medium => 'Medium',
            self::Low => 'Low',
        };
    }
}
