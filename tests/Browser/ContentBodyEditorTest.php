<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end: opens the real Content body editor in a headless browser, checks the
 * stored prose is VISIBLE in the rich editor, rewrites it, saves, and confirms the edit
 * reaches the public page.
 *
 * This is the test that would have caught the shipped defect. The Pest suite asserts the
 * mapper and the Livewire form state; only a browser can prove the editor actually
 * PAINTS the words — the failure mode was a rich field rendering an empty box while the
 * page held 21 blocks of copy.
 *
 * Auth goes through the real Filament login form: the app enforces canonical trailing
 * slashes, which 301s Dusk's slash-less `_dusk/login` helper route away, so `loginAs()`
 * can't be used here.
 */
final class ContentBodyEditorTest extends DuskTestCase
{
    public function test_editor_shows_stored_prose_and_an_edit_reaches_the_public_page(): void
    {
        $admin = User::factory()->admin()->create([
            'email' => 'dusk-body-editor@navyweek.test',
        ]);

        // The public body only renders for a slug `PageController::renderStatic()`
        // dispatches, so this drives the REAL /privacy/ row rather than an invented
        // path that would fall through to the minimal shell. No RefreshDatabase here
        // (the browser hits a separate process), so the original body is snapshotted
        // and restored in `finally`.
        $page = Page::query()->firstOrNew(['url_path' => '/privacy/']);
        $createdHere = ! $page->exists;
        $originalBlocks = $page->body_blocks;

        $page->fill([
            'slug' => 'privacy',
            'page_type' => PageType::Static,
            'title' => 'Privacy Policy',
            'is_published' => true,
            'body_blocks' => [
                ['type' => 'paragraph', 'spans' => [
                    ['text' => 'Stored prose the editor must show. '],
                    ['text' => 'VA.gov', 'url' => 'https://www.va.gov/'],
                ]],
                ['type' => 'heading', 'level' => 2, 'text' => 'DUSK SECTION'],
            ],
        ])->save();

        try {
            $this->browse(function (Browser $browser) use ($admin, $page): void {
                $browser->visit('/admin/login')
                    ->waitFor('input[type="email"]')
                    ->type('input[type="email"]', $admin->email)
                    ->type('input[type="password"]', 'password')
                    ->press('Sign in')
                    ->waitForText('Dashboard')
                    ->visit('/admin/pages/'.$page->id.'/edit')
                    ->waitForText('Content body');

                // Open the collapsed "Content body" section, then every block inside it.
                $browser->script(<<<'JS'
                    const heading = [...document.querySelectorAll('.fi-section-header-heading')]
                        .find(h => /^Content body/.test(h.textContent.trim()));
                    heading.click();
                JS);

                $browser->pause(600)->script(<<<'JS'
                    const expand = [...document.querySelectorAll('button')]
                        .find(b => /Expand all/i.test(b.textContent));
                    if (expand) expand.click();
                JS);

                $browser->pause(1500);

                // THE REGRESSION GUARD: the rich editor must have painted the stored
                // words. `.trim()` on innerText — not on the DOM or the Livewire state —
                // is what proves an editor sees them.
                $browser->waitUntil(<<<'JS'
                    (() => {
                        const editors = [...document.querySelectorAll('.fi-fo-rich-editor [contenteditable="true"], .fi-fo-rich-editor .tiptap')];
                        return editors.some(el => el.innerText.includes('Stored prose the editor must show.'));
                    })()
                JS, 15);

                // The link inside that prose survived as a real anchor, not flat text.
                $anchors = (int) $browser->script(<<<'JS'
                    const editors = [...document.querySelectorAll('.fi-fo-rich-editor [contenteditable="true"], .fi-fo-rich-editor .tiptap')];
                    const host = editors.find(el => el.innerText.includes('Stored prose the editor must show.'));
                    return host ? host.querySelectorAll('a[href="https://www.va.gov/"]').length : 0;
                JS)[0];
                $this->assertSame(1, $anchors, 'A stored link must render as an anchor inside the rich editor.');

                // The heading block's plain field is populated too.
                $headingValue = (string) $browser->script(<<<'JS'
                    const inputs = [...document.querySelectorAll('input[type="text"]')];
                    const match = inputs.find(i => i.value === 'DUSK SECTION');
                    return match ? match.value : '';
                JS)[0];
                $this->assertSame('DUSK SECTION', $headingValue, 'The heading block must be filled with its stored text.');

                // Rewrite the paragraph through the editor itself, then save.
                $browser->script(<<<'JS'
                    const editors = [...document.querySelectorAll('.fi-fo-rich-editor [contenteditable="true"], .fi-fo-rich-editor .tiptap')];
                    const host = editors.find(el => el.innerText.includes('Stored prose the editor must show.'));
                    host.focus();
                    document.execCommand('selectAll', false, null);
                    document.execCommand('insertText', false, 'Rewritten in the browser by Dusk.');
                    host.dispatchEvent(new Event('input', { bubbles: true }));
                    host.dispatchEvent(new Event('blur', { bubbles: true }));
                JS);

                $browser->pause(1200)
                    ->scrollTo('button[type="submit"]')
                    ->press('Save changes')
                    ->waitForText('Saved', 15);
            });

            // Persisted through the mapper in the stored shape, not as editor HTML.
            $blocks = $page->fresh()->body_blocks;

            $this->assertSame('paragraph', $blocks[0]['type']);
            $this->assertArrayHasKey('spans', $blocks[0], 'The paragraph must still be stored as spans.');
            $this->assertStringContainsString(
                'Rewritten in the browser by Dusk.',
                implode('', array_column($blocks[0]['spans'], 'text')),
                'The browser edit must reach the database.'
            );

            // The untouched heading block is byte-identical.
            $this->assertSame(
                ['type' => 'heading', 'level' => 2, 'text' => 'DUSK SECTION'],
                $blocks[1],
                'A block the editor never opened must not be rewritten.'
            );

            // And it is on the public page.
            $this->browse(function (Browser $browser): void {
                $browser->visit('/privacy/')
                    ->waitForText('Rewritten in the browser by Dusk.')
                    ->assertSee('DUSK SECTION');
            });
        } finally {
            if ($createdHere) {
                $page->forceDelete();
            } else {
                $page->forceFill(['body_blocks' => $originalBlocks])->save();
            }

            $admin->forceDelete();
        }
    }
}
