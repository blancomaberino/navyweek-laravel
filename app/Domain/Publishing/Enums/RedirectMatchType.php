<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * How a redirect's `from_path` matches an incoming request path.
 *
 * - Exact: the normalized path equals `from_path`.
 * - Prefix: the path equals `from_path` or starts with it (the algorithmic
 *   collapses, e.g. /navy-ranks/** → /navy-ranks/).
 */
enum RedirectMatchType: string implements HasLabel
{
    case Exact = 'exact';
    case Prefix = 'prefix';

    public function label(): string
    {
        return match ($this) {
            self::Exact => 'Exact',
            self::Prefix => 'Prefix',
        };
    }
}
