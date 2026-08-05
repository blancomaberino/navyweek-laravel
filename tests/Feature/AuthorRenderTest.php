<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateAuthorPagesAction;
use App\Domain\Publishing\Pages\GenerateVeteransDayPageAction;
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

it('resolves the byline Person @id to the custom profile path, not the family default', function () {
    // The default byline author (config site.editorial.default_author_slug = t-alford).
    $author = User::factory()->create(['slug' => 't-alford', 'credentials' => 'USNA']);
    // Their profile page, renamed by an editor to a custom canonical url_path.
    Page::factory()->create([
        'pageable_type' => (new User)->getMorphClass(),
        'pageable_id' => $author->id,
        'page_type' => PageType::Author,
        'url_path' => '/crew/alford/',
        'url_path_is_custom' => true,
    ]);

    // A page that cites this user as its byline author (veterans-day takes the default byline).
    app(GenerateVeteransDayPageAction::class)();

    authorFetch('/veterans-day/')
        ->assertOk()
        // The byline Person resolves to the PERSISTED custom path, honoring the rename…
        ->assertSee('"@id":"https://www.navyweek.org/crew/alford/#person"', false)
        // …not the synthesized family-default path.
        ->assertDontSee('/authors/t-alford/#person', false);
});

it('renders the structured career timelines, hero location and curated works over the byline list', function () {
    $author = User::factory()->create([
        'name' => 'T Madden Alford',
        'slug' => 't-alford',
        'job_title' => 'Editor, NavyWeek.org',
        'service_title' => 'Captain (O-6), U.S. Navy Reserve',
        'current_title' => 'Co-Founder & Head of Growth, Honest Paws',
        'location_city' => 'League City',
        'location_state' => 'Texas',
        'military_timeline' => [[
            'title' => 'Assistant Engineer, USS Key West (SSN-722)',
            'org' => 'United States Navy',
            'period' => 'May 2006 – Feb 2007',
            'detail' => 'Submarine officer aboard a Los Angeles-class fast-attack submarine.',
        ]],
        'civilian_timeline' => [[
            'title' => 'Co-Founder & President',
            'org' => 'Triton Well Services LLC',
            'period' => 'Mar 2014 – Jul 2015',
            'detail' => null,
        ]],
        'knows_about' => ['military discounts'],
        'profile_expertise' => ['Nuclear weapon surety'],
        'expertise_lead' => 'Topics T Madden Alford covers and reviews for NavyWeek.org:',
        'featured_works' => [[
            'url' => '/va-disability/',
            'label' => 'VA Disability: Ratings, Pay, and How to File in 2026',
            'note' => 'author',
        ]],
        'profile_reviewed_at' => '2026-05-01',
    ]);

    // A byline-derived page: the curated list must WIN over it, the way the legacy page
    // listed two hand-picked credits rather than every auto-bylined guide.
    Page::factory()->create([
        'author_id' => $author->id,
        'title' => 'Some Auto-Bylined Guide',
        'url_path' => '/discount/nike-military-veteran/',
    ]);

    app(GenerateAuthorPagesAction::class)();

    authorFetch('/authors/t-alford/')
        ->assertOk()
        // Hero: the service line and the civilian line are distinct from job_title.
        ->assertSee('Captain (O-6), U.S. Navy Reserve')
        ->assertSee('Co-Founder &amp; Head of Growth, Honest Paws', false)
        ->assertSee('League City, Texas')
        // Timelines, not the prose columns.
        ->assertSee('Assistant Engineer, USS Key West (SSN-722)')
        ->assertSee('May 2006 – Feb 2007')
        ->assertSee('Los Angeles-class fast-attack submarine')
        ->assertSee('Triton Well Services LLC')
        // The profile's own expertise list, not the compact discount byline list.
        ->assertSee('Topics T Madden Alford covers and reviews for NavyWeek.org:')
        ->assertSee('<li>Nuclear weapon surety</li>', false)
        // `knows_about` stays the byline list the discount guides cite — it is NOT a chip.
        ->assertDontSee('<li>military discounts</li>', false)
        // Curated credits replace the auto-derived byline list entirely.
        ->assertSee('VA Disability: Ratings, Pay, and How to File in 2026')
        // (It survives only in the head's ItemList JSON-LD, which AuthorPageSchema owns.)
        ->assertDontSee('>Some Auto-Bylined Guide</a>', false)
        ->assertSee('Profile last reviewed: May 2026');
});

it('falls back to the prose columns and the byline list when no structured profile is set', function () {
    $author = User::factory()->create([
        'slug' => 'erik-rivera',
        'job_title' => 'Expert Reviewer, NavyWeek.org',
        'military_service' => 'U.S. Naval Academy class of 2004.',
        'knows_about' => ['Naval Special Operations'],
    ]);
    Page::factory()->create(['reviewer_id' => $author->id, 'title' => 'Navy Ranks Reference']);

    app(GenerateAuthorPagesAction::class)();

    authorFetch('/authors/erik-rivera/')
        ->assertOk()
        ->assertSee('U.S. Naval Academy class of 2004.')
        ->assertSee('Naval Special Operations')
        ->assertSee('REVIEWS FOR NAVYWEEK.ORG')
        ->assertSee('Navy Ranks Reference')
        ->assertDontSee('Profile last reviewed');
});
