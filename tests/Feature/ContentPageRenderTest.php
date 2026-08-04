<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Pages\GenerateContentPagesAction;
use App\Domain\Publishing\Seo\ContentPageSchema;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

function contentFetch(string $path): TestResponse
{
    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

it('seeds the privacy/terms/contact/our-process content pages with an editable body', function () {
    $count = app(GenerateContentPagesAction::class)();

    expect($count)->toBe(4);
    foreach (['/privacy/', '/terms/', '/contact/', '/our-process/'] as $path) {
        $page = Page::query()->where('url_path', $path)->firstOrFail();
        expect($page->page_type)->toBe(PageType::Static)
            ->and($page->is_published)->toBeTrue()
            ->and($page->body_blocks)->toBeArray()
            ->and($page->body_blocks)->not->toBe([]);
    }
});

it('does not clobber an editor-set body on re-run', function () {
    app(GenerateContentPagesAction::class)();
    Page::query()->where('url_path', '/privacy/')->update([
        'body_blocks' => [['type' => 'paragraph', 'text' => 'Editor rewrote this.']],
    ]);

    app(GenerateContentPagesAction::class)(); // re-run

    $blocks = Page::query()->where('url_path', '/privacy/')->firstOrFail()->body_blocks;
    expect($blocks)->toBe([['type' => 'paragraph', 'text' => 'Editor rewrote this.']]);
});

it('renders a content page body from body_blocks with a breadcrumb-only graph', function () {
    app(GenerateContentPagesAction::class)();
    Page::query()->where('url_path', '/privacy/')->update([
        'body_blocks' => [
            ['type' => 'heading', 'text' => 'What we collect'],
            ['type' => 'paragraph', 'text' => 'Aggregate analytics only.'],
            ['type' => 'list', 'items' => ['No selling data', 'No ad tracking']],
        ],
    ]);

    $res = contentFetch('/privacy/')->assertOk();

    $res->assertSee('Privacy Policy')          // h1 (page title)
        ->assertSee('What we collect')
        ->assertSee('Aggregate analytics only.')
        ->assertSee('No ad tracking')
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"Organization"', false)  // prepended by SeoHead
        // Breadcrumb-only: no Article/FAQPage/WebPage nodes.
        ->assertDontSee('"@type":"Article"', false)
        ->assertDontSee('"@type":"FAQPage"', false);
});

it('builds a breadcrumb-only content-page graph', function () {
    $page = Page::factory()->create(['slug' => 'privacy', 'url_path' => '/privacy/', 'title' => 'Privacy Policy']);

    $graph = ContentPageSchema::build($page, [
        ['name' => 'Home', 'url' => '/'],
        ['name' => 'Privacy Policy', 'url' => '/privacy/'],
    ]);

    expect($graph)->toHaveCount(1)
        ->and($graph[0]['@type'])->toBe('BreadcrumbList')
        ->and($graph[0]['itemListElement'])->toHaveCount(2);
});
