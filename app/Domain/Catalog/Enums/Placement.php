<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Enums;

/**
 * On-page spots an outbound offer link can live in. Ported from the legacy
 * `Placement` union; `token()` returns the stable sub-ID token from
 * `PLACEMENT_TOKENS` (`nw-<pagetype>-<spot>`, slug-free so it stays short and
 * stable across every brand page).
 */
enum Placement: string
{
    case HeroCta = 'hero-cta';
    case StickyFooter = 'sticky-footer';
    case KeyfactsSource = 'keyfacts-source';

    /** The placement sub-ID token appended to the outbound URL. */
    public function token(): string
    {
        return match ($this) {
            self::HeroCta => 'nw-dsc-hero',
            self::StickyFooter => 'nw-dsc-footer',
            self::KeyfactsSource => 'nw-dsc-keyfacts',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::HeroCta => 'Hero CTA',
            self::StickyFooter => 'Sticky footer',
            self::KeyfactsSource => 'Key-facts source',
        };
    }
}
