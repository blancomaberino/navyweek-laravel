<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateAuthorPagesAction;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function authorFetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('renders the author profile with the ProfilePage/Person graph and the authored ItemList', function () {
    $author = User::factory()->create([
        'name' => 'T Madden Alford',
        'slug' => 't-alford',
        'job_title' => 'Editor, NavyWeek.org',
        'credentials' => "U.S. Naval Academy '02 · Navy Reserve Captain",
        'avatar_path' => '/authors/t-alford.jpg',
        'knows_about' => ['Submarine community', 'VA disability benefits'],
        'bio' => 'T Madden Alford is a U.S. Naval Academy graduate and former submarine officer.',
        'linkedin_url' => 'https://www.linkedin.com/in/t-madden-alford-8281b04',
    ]);

    // A page this author WROTE (its byline author) and a page they REVIEWED.
    Page::factory()->create([
        'author_id' => $author->id,
        'title' => 'VA Disability Benefits Guide',
        'url_path' => '/va-disability/',
    ]);
    Page::factory()->create([
        'reviewer_id' => $author->id,
        'title' => 'Navy Ranks Reference',
        'url_path' => '/navy-ranks/',
    ]);

    app(GenerateAuthorPagesAction::class)();

    $res = authorFetch('/authors/t-alford/')->assertOk();

    // Visible profile content.
    $res->assertSee('T Madden Alford')
        ->assertSee('Editor, NavyWeek.org')
        ->assertSee('former submarine officer')            // bio prose
        ->assertSee('Submarine community')                 // expertise chip
        ->assertSee('VA Disability Benefits Guide')        // "writes for" (authored)
        ->assertSee('Navy Ranks Reference')                // "reviews for" (reviewed)
        ->assertSee('linkedin.com/in/t-madden-alford', false);

    // JSON-LD: Organization (prepended by SeoHead) + the ProfilePage/Person graph + ItemList.
    $res->assertSee('"@type":"Organization"', false)
        ->assertSee('"@type":"Person"', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"ProfilePage"', false)
        ->assertSee('"@type":"ItemList"', false)
        ->assertSee('"@id":"https://www.navyweek.org/authors/t-alford/#person"', false)
        // The profile is a Person profile: og:type profile.
        ->assertSee('property="og:type" content="profile"', false);
});

it('omits the authored ItemList when the author has written nothing', function () {
    User::factory()->create(['name' => 'Erik Rivera', 'slug' => 'erik-rivera', 'bio' => 'A reviewer.']);

    app(GenerateAuthorPagesAction::class)();

    authorFetch('/authors/erik-rivera/')
        ->assertOk()
        ->assertSee('"@type":"ProfilePage"', false)
        ->assertDontSee('"@type":"ItemList"', false);
});

it('excludes author-profile pages from the writes-for list even when they carry a byline', function () {
    $author = User::factory()->create(['slug' => 't-alford']);

    // A real article this user wrote — SHOULD be listed.
    Page::factory()->create([
        'author_id' => $author->id,
        'page_type' => PageType::Static,
        'title' => 'A Real Guide',
    ]);
    // A defensively byline-carrying author-profile page must NEVER appear as one of
    // their "articles" — the page_type filter is what guarantees that.
    Page::factory()->create([
        'author_id' => $author->id,
        'page_type' => PageType::Author,
        'title' => 'Some Author Profile',
    ]);

    $authored = app(PageRepositoryInterface::class)->publishedIndexableAuthoredBy($author->id);

    expect($authored->pluck('title')->all())->toBe(['A Real Guide'])
        ->and($authored->every(fn (Page $p): bool => $p->page_type !== PageType::Author))->toBeTrue();
});
