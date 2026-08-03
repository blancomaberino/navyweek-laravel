<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateAuthorPagesAction;
use App\Models\User;

it('generates an /authors/{slug}/ page for every user with a public profile slug', function () {
    User::factory()->create(['name' => 'T Madden Alford', 'slug' => 't-alford']);
    User::factory()->create(['name' => 'Erik Rivera', 'slug' => 'erik-rivera']);
    // An ops account with no byline slug must NOT get a profile page.
    User::factory()->create(['name' => 'Ops Admin', 'slug' => null]);

    $count = app(GenerateAuthorPagesAction::class)();

    expect($count)->toBe(2);

    $page = Page::query()->where('generation_key', 'author:t-alford')->firstOrFail();
    expect($page->page_type)->toBe(PageType::Author)
        ->and($page->url_path)->toBe('/authors/t-alford/')
        ->and($page->is_published)->toBeTrue()
        ->and($page->og_type)->toBe('profile')
        ->and($page->pageable)->toBeInstanceOf(User::class)
        ->and($page->pageable->slug)->toBe('t-alford')
        ->and($page->title)->toContain('T Madden Alford');

    // No profile page for the slug-less ops account.
    expect(Page::query()->where('page_type', PageType::Author)->count())->toBe(2);
});

it('is idempotent and honors the build clock (date_published preserved on re-run)', function () {
    User::factory()->create(['slug' => 't-alford']);

    app(GenerateAuthorPagesAction::class)();
    $first = Page::query()->where('generation_key', 'author:t-alford')->firstOrFail();
    $published = $first->date_published;

    // Re-run after the profile already exists — no duplicate, date_published untouched.
    app(GenerateAuthorPagesAction::class)();

    expect(Page::query()->where('generation_key', 'author:t-alford')->count())->toBe(1)
        ->and(Page::query()->where('generation_key', 'author:t-alford')->firstOrFail()->date_published->equalTo($published))->toBeTrue();
});

it('uses the migrated bio for the meta description when present', function () {
    User::factory()->create(['slug' => 't-alford', 'bio' => 'Former submarine officer who writes on Navy service.']);

    app(GenerateAuthorPagesAction::class)();

    expect(Page::query()->where('generation_key', 'author:t-alford')->firstOrFail()->meta_description)
        ->toContain('Former submarine officer');
});
