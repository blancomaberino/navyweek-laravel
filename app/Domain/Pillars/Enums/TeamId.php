<?php

declare(strict_types=1);

namespace App\Domain\Pillars\Enums;

/**
 * A U.S. military flight-demonstration squadron (port of the legacy `TeamId`
 * union). Each team has a hub at `/{team}/` and per-city guides at `/{team}/{city}/`.
 */
enum TeamId: string
{
    case BlueAngels = 'blue-angels';
    case Thunderbirds = 'thunderbirds';

    public function label(): string
    {
        return match ($this) {
            self::BlueAngels => 'Blue Angels',
            self::Thunderbirds => 'Thunderbirds',
        };
    }
}
