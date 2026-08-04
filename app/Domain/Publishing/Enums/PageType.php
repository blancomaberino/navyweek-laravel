<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Enums;

use App\Domain\Shared\Enums\HasLabel;

/**
 * The kind of route a `pages` row represents. Drives which body editor Filament
 * shows and which JSON-LD required-types apply. Mirrors the legacy page families.
 */
enum PageType: string implements HasLabel
{
    case Home = 'home';
    case DiscountBrand = 'discount_brand';
    case DiscountCategoryHub = 'discount_category_hub';
    case LocalDiscount = 'local_discount';
    case Base = 'base';
    case BaseHub = 'base_hub';
    case BaseStateHub = 'base_state_hub';
    case BaseCountryHub = 'base_country_hub';
    case BaseOverseasHub = 'base_overseas_hub';
    case Rank = 'rank';
    case Rating = 'rating';
    case Designator = 'designator';
    case DesignatorHub = 'designator_hub';
    case DesignatorCommunityHub = 'designator_community_hub';
    case NavyWeekCity = 'navy_week_city';
    case FleetWeek = 'fleet_week';
    case AirShow = 'air_show';
    case JetTeam = 'jet_team';
    case JetTeamCity = 'jet_team_city';
    case VeteransDayHub = 'veterans_day_hub';
    case Author = 'author';
    case Static = 'static';

    public function label(): string
    {
        return match ($this) {
            self::Home => 'Home',
            self::DiscountBrand => 'Discount brand',
            self::DiscountCategoryHub => 'Discount category hub',
            self::LocalDiscount => 'Local discount',
            self::Base => 'Base',
            self::BaseHub => 'Navy bases directory',
            self::BaseStateHub => 'Navy bases by state',
            self::BaseCountryHub => 'Navy bases by country',
            self::BaseOverseasHub => 'Navy bases overseas',
            self::Rank => 'Rank',
            self::Rating => 'Rating',
            self::Designator => 'Officer designator',
            self::DesignatorHub => 'Officer designators hub',
            self::DesignatorCommunityHub => 'Officer designator community hub',
            self::NavyWeekCity => 'Navy Week city',
            self::FleetWeek => 'Fleet Week',
            self::AirShow => 'Air show',
            self::JetTeam => 'Jet team',
            self::JetTeamCity => 'Jet team city',
            self::VeteransDayHub => 'Veterans Day hub',
            self::Author => 'Author profile',
            self::Static => 'Static',
        };
    }
}
