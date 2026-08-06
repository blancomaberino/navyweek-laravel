<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Livewire\Livewire;

/**
 * Drives the REAL Filament form end to end — fill from a stored body, edit, save — so
 * the guarantee covers the wiring (fill/save hooks, Builder state shape) and not just
 * the mapper in isolation.
 */
beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

/**
 * Built by hand, not via `$this->get()`: Laravel's test client trims the trailing
 * slash, so the request would only ever hit the slash-normalising 301.
 */
function renderPrivacy(): string
{
    $response = app()->handle(Request::create('http://localhost/privacy/', 'GET'));

    expect($response->getStatusCode())->toBe(200);

    return (string) $response->getContent();
}

/** The prose inside a rich editor's state, whatever internal shape it holds. */
function editorText(mixed $content): string
{
    return html_entity_decode(
        strip_tags(is_string($content) ? $content : json_encode($content, JSON_UNESCAPED_UNICODE)),
        ENT_QUOTES | ENT_HTML5,
    );
}

/**
 * Rewrite the first block's prose through the real form and save, the way an editor
 * would.
 */
function saveFirstBlockContent(Page $page, string $html): void
{
    $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);

    $state = $component->get('data');
    $state['body_blocks'][array_key_first($state['body_blocks'])]['data']['content'] = $html;

    $component->set('data', $state)
        ->call('save')
        ->assertHasNoFormErrors();
}

function contentPage(array $blocks): Page
{
    return Page::query()->create([
        'url_path' => '/privacy/',
        'slug' => 'privacy',
        'page_type' => PageType::Static,
        'title' => 'Privacy Policy',
        'is_published' => true,
        'body_blocks' => $blocks,
    ]);
}

it('fills the editor with the prose a page actually stores', function (): void {
    $page = contentPage([
        ['type' => 'paragraph', 'spans' => [['text' => 'Welcome to NavyWeek.org.']]],
        ['type' => 'heading', 'level' => 2, 'text' => 'INTRODUCTION'],
    ]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->assertFormSet(function (array $state): bool {
            // Filament keys builder items by UUID; the order is the array order.
            $blocks = array_values($state['body_blocks']);

            // The shipped defect: this box was empty for every stored paragraph.
            return str_contains(editorText($blocks[0]['data']['content']), 'Welcome to NavyWeek.org.')
                && $blocks[0]['type'] === 'paragraph'
                && $blocks[1]['data']['text'] === 'INTRODUCTION';
        });
});

it('saves an edited paragraph back as spans and leaves its neighbours untouched', function (): void {
    $page = contentPage([
        ['type' => 'paragraph', 'spans' => [['text' => 'Original copy.']]],
        ['type' => 'heading', 'level' => 2, 'text' => 'INFORMATION WE COLLECT'],
        ['type' => 'list', 'ordered' => false, 'items' => [['spans' => [['text' => 'Browser type and version']]]]],
    ]);

    saveFirstBlockContent($page, '<p>Rewritten copy with a <strong>bold</strong> phrase.</p>');

    expect($page->refresh()->body_blocks)->toBe([
        ['type' => 'paragraph', 'spans' => [
            ['text' => 'Rewritten copy with a '],
            ['text' => 'bold', 'bold' => true],
            ['text' => ' phrase.'],
        ]],
        ['type' => 'heading', 'level' => 2, 'text' => 'INFORMATION WE COLLECT'],
        ['type' => 'list', 'ordered' => false, 'items' => [['spans' => [['text' => 'Browser type and version']]]]],
    ]);
});

it('leaves an untouched body byte-identical after a save', function (): void {
    $blocks = bodyBlockCorpus()['privacy'];

    $page = contentPage($blocks);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->body_blocks)->toBe($blocks);
});

it('renders an edit made in the CMS on the public page', function (): void {
    $page = contentPage([
        ['type' => 'paragraph', 'spans' => [['text' => 'Original copy.']]],
    ]);

    saveFirstBlockContent($page, '<p>Copy an editor just published.</p>');

    // The whole point of the CMS: the edit is on the site, not just in the database.
    expect(renderPrivacy())->toContain('Copy an editor just published.');
});

it('does not execute markup an editor pastes into the body', function (): void {
    $page = contentPage([['type' => 'paragraph', 'spans' => [['text' => 'Safe.']]]]);

    saveFirstBlockContent($page, '<p>Before<script>alert(1)</script>After</p>');

    // The surrounding prose is kept; the script subtree is dropped whole, so its
    // source never reaches the stored body in ANY form.
    expect($page->refresh()->body_blocks[0]['spans'][0]['text'])
        ->toBe('BeforeAfter');

    expect(renderPrivacy())
        ->toContain('BeforeAfter')
        ->not->toContain('alert(1)');
});

it('stores an emptied body as null, which the generators branch on', function (): void {
    // Generate*Action treats null/[] as "never seeded" and will re-seed; the column
    // must land as NULL rather than an empty array so that check keeps working.
    $page = contentPage([['type' => 'paragraph', 'spans' => [['text' => 'Delete me.']]]]);

    $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
    $component->set('data.body_blocks', [])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->body_blocks)->toBeNull();
});

it('stores a javascript: link an editor pastes without it becoming executable', function (): void {
    $page = contentPage([['type' => 'paragraph', 'spans' => [['text' => 'Safe.']]]]);

    saveFirstBlockContent($page, '<p><a href="javascript:alert(1)">click me</a></p>');

    // LinkUrl::sanitize() is the renderer-side gate (repo policy: editor-supplied
    // values are untrusted output). It must neutralise the scheme, while the link
    // text itself still renders.
    expect(renderPrivacy())
        ->toContain('click me')
        ->not->toContain('javascript:alert(1)');
});
