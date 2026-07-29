<?php

declare(strict_types=1);

namespace App\Domain\Shared\Enums;

/**
 * A backed enum that exposes a human-readable label for its cases. Framework-
 * agnostic on purpose — the presentation layers (Filament, Blade) map these to
 * their own option/label shapes without the domain depending on them.
 */
interface HasLabel
{
    public function label(): string;
}
