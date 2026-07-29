<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * Confidence in a verified claim. Shared vocabulary: a Research brief rates its
 * facts overall, and an individual Source rates its own reliability (mirrors the
 * per-fact High/Medium/Low column in the legacy research briefs).
 */
enum ConfidenceLevel: string implements HasLabel
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
