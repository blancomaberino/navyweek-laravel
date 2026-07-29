<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * Eligible-audience tags a Connection (and later an Offer) can target.
 *
 * Ported verbatim from the legacy queue `audiences[]` vocabulary. Stored on
 * `connections.audiences` as a JSON array and cast to a collection of this enum.
 */
enum Audience: string implements HasLabel
{
    case Military = 'military';
    case Veteran = 'veteran';
    case Student = 'student';
    case Teacher = 'teacher';
    case Healthcare = 'healthcare';
    case Government = 'government';
    case Senior = 'senior';

    public function label(): string
    {
        return match ($this) {
            self::Military => 'Military',
            self::Veteran => 'Veteran',
            self::Student => 'Student',
            self::Teacher => 'Teacher',
            self::Healthcare => 'Healthcare',
            self::Government => 'Government',
            self::Senior => 'Senior',
        };
    }
}
