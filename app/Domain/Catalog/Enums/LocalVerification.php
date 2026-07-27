<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * How a local-business offer is verified at the point of sale. Ported verbatim
 * from the legacy `LocalVerification` union — most local offers are in-person
 * (show a military ID); a few gate an online reservation behind SheerID/ID.me.
 *
 * Deliberately distinct from Catalog's `VerificationProvider` despite the shared
 * values: this is a *point-of-sale mode* axis (in-store vs reservation vs online
 * provider), whereas `VerificationProvider` names the *national gateway* (incl.
 * GovX/VerifyPass, which have no local-storefront meaning). Do not merge them —
 * doing so would leak national-only providers into the local vocabulary.
 */
enum LocalVerification: string
{
    case InStoreId = 'In-store ID';
    case ReservationId = 'Reservation + ID';
    case SheerId = 'SheerID';
    case IdMe = 'ID.me';
    case Other = 'Other';

    public function label(): string
    {
        return $this->value;
    }
}
