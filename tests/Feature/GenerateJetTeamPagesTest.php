<?php

declare(strict_types=1);

use App\Domain\Pillars\Models\JetTeam;
use App\Domain\Pillars\Models\JetTeamCity;
use App\Domain\Pillars\Pages\GenerateJetTeamPagesAction;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;

function jetTeamEditorial(): void
{
    User::factory()->create(['slug' => 't-alford', 'name' => 'T Madden Alford']);
    User::factory()->create(['slug' => 'erik-rivera', 'name' => 'Erik Rivera']);
}

it('generates a hub per team + a page per published city, skipping unpublished', function () {
    jetTeamEditorial();
    $team = JetTeam::factory()->create(); // Blue Angels, base_path /blue-angels
    JetTeamCity::factory()->for($team, 'team')->create(['slug' => 'anchorage', 'city' => 'Anchorage']);
    JetTeamCity::factory()->for($team, 'team')->unpublished()->create(['slug' => 'draft', 'city' => 'Draft']);

    $count = app(GenerateJetTeamPagesAction::class)();

    expect($count)->toBe(2); // hub + 1 published city

    $hub = Page::query()->where('url_path', '/blue-angels/')->firstOrFail();
    expect($hub->page_type)->toBe(PageType::JetTeam)
        ->and($hub->pageable)->toBeInstanceOf(JetTeam::class);

    $city = Page::query()->where('url_path', '/blue-angels/anchorage/')->firstOrFail();
    expect($city->page_type)->toBe(PageType::JetTeamCity)
        ->and($city->pageable)->toBeInstanceOf(JetTeamCity::class)
        ->and($city->author_id)->not->toBeNull();   // byline for the Person nodes

    expect(Page::query()->where('url_path', '/blue-angels/draft/')->exists())->toBeFalse();
});
