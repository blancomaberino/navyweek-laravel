<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * Identity-verification provider used to unlock an offer. Ported verbatim from
 * the legacy `VerificationProvider` union; backing values match the strings the
 * legacy records (and rendered copy) already use.
 */
enum VerificationProvider: string
{
    case IdMe = 'ID.me';
    case GovX = 'GovX';
    case SheerId = 'SheerID';
    case VerifyPass = 'VerifyPass';
    case InStoreId = 'In-store ID';
    case Other = 'Other';

    public function label(): string
    {
        return $this->value;
    }
}
