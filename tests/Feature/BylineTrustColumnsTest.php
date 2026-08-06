<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The per-page byline columns exist for a YMYL reason, not a cosmetic one: the VA
 * guides must publish "…not a VA-accredited representative" rather than the
 * reviewer's generic bio, and an author-only page must stop naming a reviewer it
 * never had. Both fall back to the user's own values, so a regression that ignores
 * the page-level override silently republishes the WEAKER disclaimer on exactly the
 * benefits pages the project's non-negotiables single out — with a green suite.
 *
 * Requests are built by hand: Laravel's test client trims the trailing slash, so
 * `$this->get('/x/')` would only ever exercise the slash-normalising redirect.
 */
function renderBylinePage(array $pageAttributes): string
{
    // Unique per call: the helper runs twice inside a single test.
    $n = User::query()->count();
    $author = User::factory()->create(['slug' => "a-author-{$n}", 'name' => 'A Author', 'credentials' => 'GENERIC AUTHOR BIO']);
    $reviewer = User::factory()->create(['slug' => "a-reviewer-{$n}", 'name' => 'A Reviewer', 'credentials' => 'GENERIC REVIEWER BIO']);

    Page::query()->where('url_path', '/navy-reference/')->delete();
    $page = Page::factory()->create(array_merge([
        'page_type' => PageType::NavyReferenceHub,
        'url_path' => '/navy-reference/',
        'slug' => 'navy-reference',
        'is_published' => true,
        'author_id' => $author->id,
        'reviewer_id' => $reviewer->id,
        'last_reviewed' => '2026-05-25',
    ], $pageAttributes));
    $page->save();

    $response = app()->handle(Request::create('http://localhost/navy-reference/', 'GET'));
    expect($response->getStatusCode())->toBe(200);

    return (string) $response->getContent();
}

it('prefers the PAGE credentials over the user bio when set', function () {
    $html = renderBylinePage([
        'reviewer_credentials' => 'not a VA-accredited representative',
        'author_credentials' => 'PAGE AUTHOR CREDENTIAL',
    ]);

    expect($html)->toContain('not a VA-accredited representative')
        ->and($html)->toContain('PAGE AUTHOR CREDENTIAL')
        ->and($html)->not->toContain('GENERIC REVIEWER BIO')
        ->and($html)->not->toContain('GENERIC AUTHOR BIO');
});

it('falls back to the user bio when the page sets no credentials', function () {
    $html = renderBylinePage([]);

    expect($html)->toContain('GENERIC REVIEWER BIO')
        ->and($html)->toContain('GENERIC AUTHOR BIO');
});

it('drops the reviewer row on an author-only page while keeping reviewer_id set', function () {
    // A flag, not a null reviewer_id — EditorialTeamSeeder back-fills any null
    // reviewer, so clearing the id would silently come back on the next seed.
    $html = renderBylinePage(['shows_reviewer' => false]);

    expect($html)->toContain('Written by')
        ->and($html)->not->toContain('Reviewed by')
        ->and($html)->not->toContain('GENERIC REVIEWER BIO');
});

it('drops the editorial-process row when the page opts out', function () {
    expect(renderBylinePage(['shows_process_link' => true]))->toContain('Our editorial process');
    expect(renderBylinePage(['shows_process_link' => false]))->not->toContain('Our editorial process');
});

it('renders page-specific editorial-policy bullets over the house wording', function () {
    $html = renderBylinePage([
        'editorial_source_priority' => 'PAGE SOURCE PRIORITY',
        'editorial_independence' => 'PAGE INDEPENDENCE CLAUSE',
        'editorial_reviewer_note' => 'PAGE REVIEWER DISCLAIMER',
        'editorial_corrections' => 'PAGE CORRECTIONS CLAUSE',
        'editorial_not_advice' => 'PAGE NOT-ADVICE CLAUSE',
        'corrections_note' => 'PAGE CORRECTIONS NOTE',
    ]);

    expect($html)->toContain('PAGE INDEPENDENCE CLAUSE')
        ->and($html)->toContain('PAGE REVIEWER DISCLAIMER')
        ->and($html)->toContain('PAGE CORRECTIONS CLAUSE')
        ->and($html)->toContain('PAGE NOT-ADVICE CLAUSE')
        ->and($html)->toContain('PAGE CORRECTIONS NOTE')
        // The generic wording must NOT also render alongside it.
        ->and($html)->not->toContain('not affiliated with the U.S. Navy, the Department of Defense');
});
