<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Shared\Enums\HasLabel;
use BackedEnum;

/**
 * Maps a set of backed, labelled domain enum cases to Filament's
 * `value => label` option array. Keeps the domain enums framework-agnostic (they
 * carry {@see HasLabel}, not Filament's own contract) while every resource
 * form/table/filter builds its options the same way.
 */
final class EnumOptions
{
    /**
     * @param  array<int, HasLabel&BackedEnum>  $cases
     * @return array<int|string, string>
     */
    public static function map(array $cases): array
    {
        $options = [];
        foreach ($cases as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
