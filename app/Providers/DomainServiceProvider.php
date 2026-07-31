<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Catalog\Repositories\AffiliateLinkRepositoryInterface;
use App\Domain\Catalog\Repositories\AffiliateNetworkRepositoryInterface;
use App\Domain\Catalog\Repositories\DiscountCategoryRepositoryInterface;
use App\Domain\Catalog\Repositories\EloquentAffiliateLinkRepository;
use App\Domain\Catalog\Repositories\EloquentAffiliateNetworkRepository;
use App\Domain\Catalog\Repositories\EloquentDiscountCategoryRepository;
use App\Domain\Catalog\Repositories\EloquentLocalDiscountRepository;
use App\Domain\Catalog\Repositories\EloquentOfferRepository;
use App\Domain\Catalog\Repositories\EloquentVeteransDayMealRepository;
use App\Domain\Catalog\Repositories\LocalDiscountRepositoryInterface;
use App\Domain\Catalog\Repositories\OfferRepositoryInterface;
use App\Domain\Catalog\Repositories\VeteransDayMealRepositoryInterface;
use App\Domain\Crm\Repositories\ConnectionRepositoryInterface;
use App\Domain\Crm\Repositories\EloquentConnectionRepository;
use App\Domain\Pillars\Repositories\AirShowRepositoryInterface;
use App\Domain\Pillars\Repositories\BaseRepositoryInterface;
use App\Domain\Pillars\Repositories\EloquentAirShowRepository;
use App\Domain\Pillars\Repositories\EloquentBaseRepository;
use App\Domain\Pillars\Repositories\EloquentFleetWeekRepository;
use App\Domain\Pillars\Repositories\EloquentJetTeamRepository;
use App\Domain\Pillars\Repositories\EloquentNavyWeekEventRepository;
use App\Domain\Pillars\Repositories\EloquentRankRepository;
use App\Domain\Pillars\Repositories\FleetWeekRepositoryInterface;
use App\Domain\Pillars\Repositories\JetTeamRepositoryInterface;
use App\Domain\Pillars\Repositories\NavyWeekEventRepositoryInterface;
use App\Domain\Pillars\Repositories\RankRepositoryInterface;
use App\Domain\Publishing\Events\PageUrlChanged;
use App\Domain\Publishing\Listeners\CreateRedirectListener;
use App\Domain\Publishing\Repositories\EloquentPageRepository;
use App\Domain\Publishing\Repositories\EloquentRedirectRepository;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Repositories\RedirectRepositoryInterface;
use App\Domain\Research\Repositories\EloquentResearchRepository;
use App\Domain\Research\Repositories\EloquentSkillRepository;
use App\Domain\Research\Repositories\ResearchRepositoryInterface;
use App\Domain\Research\Repositories\SkillRepositoryInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Binds every repository interface to its Eloquent implementation.
 *
 * The rebuild uses the repository pattern (one interface per aggregate —
 * ConnectionRepositoryInterface, OfferRepositoryInterface, PageRepositoryInterface,
 * ResearchRepositoryInterface, RedirectRepositoryInterface, plus one per pillar).
 * Callers depend on the interface; Eloquent stays an implementation detail behind
 * the binding registered here. Add bindings as each aggregate lands in Phase 2.
 */
class DomainServiceProvider extends ServiceProvider
{
    /**
     * Interface => concrete implementation. Populated as aggregates are built.
     *
     * @var array<class-string, class-string>
     */
    public array $repositories = [
        RedirectRepositoryInterface::class => EloquentRedirectRepository::class,
        PageRepositoryInterface::class => EloquentPageRepository::class,
        ConnectionRepositoryInterface::class => EloquentConnectionRepository::class,
        OfferRepositoryInterface::class => EloquentOfferRepository::class,
        AffiliateNetworkRepositoryInterface::class => EloquentAffiliateNetworkRepository::class,
        AffiliateLinkRepositoryInterface::class => EloquentAffiliateLinkRepository::class,
        DiscountCategoryRepositoryInterface::class => EloquentDiscountCategoryRepository::class,
        VeteransDayMealRepositoryInterface::class => EloquentVeteransDayMealRepository::class,
        LocalDiscountRepositoryInterface::class => EloquentLocalDiscountRepository::class,
        ResearchRepositoryInterface::class => EloquentResearchRepository::class,
        SkillRepositoryInterface::class => EloquentSkillRepository::class,
        BaseRepositoryInterface::class => EloquentBaseRepository::class,
        RankRepositoryInterface::class => EloquentRankRepository::class,
        NavyWeekEventRepositoryInterface::class => EloquentNavyWeekEventRepository::class,
        FleetWeekRepositoryInterface::class => EloquentFleetWeekRepository::class,
        AirShowRepositoryInterface::class => EloquentAirShowRepository::class,
        JetTeamRepositoryInterface::class => EloquentJetTeamRepository::class,
    ];

    public function register(): void
    {
        foreach ($this->repositories as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }

    public function boot(): void
    {
        // Editable URLs: a page rename auto-creates its 301 (+ collapses chains).
        // The listener lives under app/Domain, outside Laravel's default listener
        // auto-discovery path, so it is wired explicitly here.
        Event::listen(PageUrlChanged::class, CreateRedirectListener::class);
    }
}
