<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Whether a redemption step applies to the online or the in-store flow. Merges
 * the legacy `redeemOnline[]` / `redeemInStore[]` split into one discriminator.
 */
enum RedemptionChannel: string
{
    case Online = 'online';
    case InStore = 'in_store';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'Online',
            self::InStore => 'In-store',
        };
    }
}
