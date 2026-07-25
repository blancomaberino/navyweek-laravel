<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

/**
 * The kind of route a `pages` row represents. Drives which body editor Filament
 * shows and which JSON-LD required-types apply. Mirrors the legacy page families.
 */
enum PageType: string
{
    case DiscountBrand = 'discount_brand';
    case DiscountCategoryHub = 'discount_category_hub';
    case LocalDiscount = 'local_discount';
    case Base = 'base';
    case Rank = 'rank';
    case NavyWeekCity = 'navy_week_city';
    case FleetWeek = 'fleet_week';
    case AirShow = 'air_show';
    case JetTeam = 'jet_team';
    case JetTeamCity = 'jet_team_city';
    case VeteransDayHub = 'veterans_day_hub';
    case Static = 'static';
}
