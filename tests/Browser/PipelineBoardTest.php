<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Crm\Enums\ConnectionStatus;
use App\Domain\Crm\Models\Connection;
use App\Models\User;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end: loads the real /admin/pipeline-board Filament page in a headless browser
 * (against the running app + its DB) and asserts what the Pest feature test can NOT —
 * that the board actually renders as a Kanban (horizontal columns of cards) rather than
 * the flat vertical stack it collapsed to when the custom view's styles weren't applied,
 * and that moving a card through the per-card <select> writes through to the connection's
 * status. Complements tests/Feature/PipelineBoardTest.php (which asserts the component
 * logic over the HTTP kernel but says nothing about layout or CSS).
 *
 * Auth goes through the real Filament login form: the app enforces canonical trailing
 * slashes, which 301s Dusk's slash-less `_dusk/login` helper route away, so `loginAs()`
 * can't be used here.
 */
final class PipelineBoardTest extends DuskTestCase
{
    public function test_board_renders_as_columns_with_cards_and_a_move_works(): void
    {
        // Distinctive, self-created fixtures so the assertions hold regardless of whatever
        // else is seeded in the Dusk database. No RefreshDatabase here (the browser hits a
        // separate process), so the rows are committed and cleaned up explicitly below.
        // The factory's default password is "password".
        $admin = User::factory()->admin()->create([
            'email' => 'dusk-pipeline-admin@navyweek.test',
        ]);

        // A very high volume so the card sorts to the top of its column (which is capped at
        // COLUMN_LIMIT cards, ordered by total_volume desc) and is guaranteed to render even
        // when the Dusk database already holds a full pipeline of seeded connections.
        $pendingCard = Connection::factory()->create([
            'brand' => 'ZZ Dusk Pending Brand',
            'category' => 'Retailers',
            'total_volume' => 8_888_888,
            'status' => ConnectionStatus::Pending,
        ]);
        $publishedCard = Connection::factory()->create([
            'brand' => 'ZZ Dusk Published Brand',
            'status' => ConnectionStatus::Published,
        ]);

        try {
            $this->browse(function (Browser $browser) use ($admin): void {
                $browser->visit('/admin/login')
                    ->waitFor('input[type="email"]')
                    ->type('input[type="email"]', $admin->email)
                    ->type('input[type="password"]', 'password')
                    ->press('Sign in')
                    ->waitForText('Dashboard')                 // sidebar nav → logged in
                    ->visit('/admin/pipeline-board')
                    ->waitForText('Pipeline board')
                    ->assertTitleContains('Pipeline board')
                    ->assertPresent('.nw-kanban')
                    ->assertSee('ZZ Dusk Pending Brand')        // a card rendered
                    ->assertSee('8,888,888 vol')                // its volume line
                    ->assertSee(ConnectionStatus::NeedsReverify->label()); // a column header

                // One column per status, each a card container — not a single stacked list.
                $columnCount = (int) $browser->script(
                    'return document.querySelectorAll(".nw-kanban__col").length;'
                )[0];
                $this->assertSame(
                    count(ConnectionStatus::cases()),
                    $columnCount,
                    'The board must render exactly one column per pipeline status.'
                );

                // The regression guard: the columns are laid out HORIZONTALLY (a real
                // Kanban), not stacked in one vertical column. The first two columns share
                // a top edge and the second sits to the right of the first, and the board
                // itself is a flex row that overflows horizontally.
                $layout = json_decode((string) $browser->script(<<<'JS'
                    const board = document.querySelector('.nw-kanban');
                    const cols = document.querySelectorAll('.nw-kanban__col');
                    const a = cols[0].getBoundingClientRect();
                    const b = cols[1].getBoundingClientRect();
                    return JSON.stringify({
                        flexDirection: getComputedStyle(board).flexDirection,
                        sameTop: Math.abs(a.top - b.top) < 2,
                        secondIsToTheRight: b.left > a.right - 2,
                        horizontalOverflow: board.scrollWidth > board.clientWidth,
                    });
                JS)[0], true);

                $this->assertSame('row', $layout['flexDirection'], 'The board must be a flex row.');
                $this->assertTrue($layout['sameTop'], 'Columns must sit side by side (same top), not stacked.');
                $this->assertTrue($layout['secondIsToTheRight'], 'The second column must be to the right of the first.');
                $this->assertTrue($layout['horizontalOverflow'], 'The board must scroll horizontally when the columns overflow.');

                // The pending card lives under the Pending column, styled as a card.
                $inPending = (int) $browser->script(<<<'JS'
                    const cols = [...document.querySelectorAll('.nw-kanban__col')];
                    const pending = cols.find(c => c.querySelector('.nw-kanban__title')?.innerText.includes('Pending'));
                    return [...pending.querySelectorAll('.nw-kanban__card .nw-kanban__brand')]
                        .filter(el => el.innerText.trim() === 'ZZ Dusk Pending Brand').length;
                JS)[0];
                $this->assertSame(1, $inPending, 'The pending brand must render as a card inside the Pending column.');

                // A move works: choosing "Published" on the pending card's <select> moves it
                // through the repository, and the card re-renders under the Published column.
                $browser->select(
                    "select[aria-label='Move ZZ Dusk Pending Brand to another status']",
                    ConnectionStatus::Published->value,
                )->waitUntil(<<<'JS'
                    (() => {
                        const cols = [...document.querySelectorAll('.nw-kanban__col')];
                        const published = cols.find(c => c.querySelector('.nw-kanban__title')?.innerText.includes('Published'));
                        if (!published) return false;
                        return [...published.querySelectorAll('.nw-kanban__card .nw-kanban__brand')]
                            .some(el => el.innerText.trim() === 'ZZ Dusk Pending Brand');
                    })()
                JS);
            });

            // The move was persisted, not just reflected in the DOM.
            $this->assertSame(
                ConnectionStatus::Published,
                $pendingCard->fresh()->status,
                'Moving the card must persist the new status through the repository.'
            );
        } finally {
            Connection::withTrashed()
                ->whereIn('id', [$pendingCard->id, $publishedCard->id])
                ->forceDelete();
            $admin->forceDelete();
        }
    }
}
