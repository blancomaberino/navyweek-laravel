<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Database\Seeders\EditorialTeamSeeder;

it('seeds the two editorial users with their public byline profile', function () {
    $this->seed(EditorialTeamSeeder::class);

    $author = User::query()->where('slug', 't-alford')->firstOrFail();
    $reviewer = User::query()->where('slug', 'erik-rivera')->firstOrFail();

    expect($author->name)->toBe('T Madden Alford')
        ->and($author->job_title)->toBe('Editor, NavyWeek.org')
        ->and($author->knows_about)->toContain('military discounts')
        ->and($reviewer->name)->toBe('Erik Rivera')
        ->and($reviewer->credentials)->toContain('EOD');
});

it('is idempotent — reseeding does not duplicate the editorial users', function () {
    $this->seed(EditorialTeamSeeder::class);
    $this->seed(EditorialTeamSeeder::class);

    expect(User::query()->whereIn('slug', ['t-alford', 'erik-rivera'])->count())->toBe(2);
});

it('back-fills the default byline onto pages that have none', function () {
    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => 'nike-military-veteran',
        'url_path' => '/discount/nike-military-veteran/',
        'title' => 'Nike Military & Veteran Discount 2026',
        'is_published' => true,
    ]);

    $this->seed(EditorialTeamSeeder::class);

    $page->refresh();
    expect($page->author?->slug)->toBe('t-alford')
        ->and($page->reviewer?->slug)->toBe('erik-rivera');
});

it('never clobbers a page that already has an assigned byline', function () {
    $customAuthor = User::factory()->create(['slug' => 'guest-writer']);
    $page = Page::create([
        'page_type' => PageType::DiscountBrand,
        'slug' => 'adidas-military-veteran',
        'url_path' => '/discount/adidas-military-veteran/',
        'title' => 'adidas Military & Veteran Discount 2026',
        'is_published' => true,
        'author_id' => $customAuthor->id,
    ]);

    $this->seed(EditorialTeamSeeder::class);

    $page->refresh();
    // The admin-set author stays; only the empty reviewer slot is back-filled.
    expect($page->author?->slug)->toBe('guest-writer')
        ->and($page->reviewer?->slug)->toBe('erik-rivera');
});
