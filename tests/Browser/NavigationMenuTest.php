<?php

declare(strict_types=1);

namespace Tests\Browser;

use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Models\MenuItem;
use Illuminate\Support\Facades\Artisan;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end: loads a real page in a headless browser (against the running app +
 * its seeded DB) and asserts what Pest feature tests can NOT — that the header and
 * footer navigation actually render, are styled by the design system, navigate,
 * and are genuinely DB-driven (an admin-side menu change shows up on reload). The
 * Browser suite runs against the dev DB, which the NavigationSeeder has populated.
 */
final class NavigationMenuTest extends DuskTestCase
{
    public function test_the_editable_header_and_footer_nav_render_and_navigate(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/veterans-day/free-meals/')
                ->assertSee('NAVYWEEK')                          // brand chrome
                ->assertPresent('.nw-header .nw-desktop-nav')     // primary nav container
                ->assertPresent('.nw-footer')                    // footer chrome
                // Uppercased by CSS, and assertSeeIn matches VISIBLE text — the
                // sibling assertions in this file already spell their labels that way.
                ->assertSeeIn('.nw-footer', 'NAVY REFERENCE')    // a seeded footer column heading
                ->assertPresent('.nw-footer-legal');             // legal row

            // The design system is actually applied (not just present in markup).
            $cssLinked = $browser->script(
                'return !!document.querySelector(\'link[href*="/build/assets/app-"]\');'
            )[0];
            $this->assertTrue($cssLinked, 'The built app stylesheet must be linked in the head.');

            $bodyBg = $browser->script('return getComputedStyle(document.body).backgroundColor;')[0];
            $this->assertSame('rgb(10, 22, 40)', $bodyBg, 'Body background must be Fleet Navy (#0A1628).');

            // The bar renders the seeded header menu — the REAL one. The previous
            // `>= 7` here was calibrated to a seven-link placeholder menu that rendered
            // nowhere; the live bar is five links plus the Events dropdown trigger.
            $labels = $browser->script(
                'return Array.from(document.querySelectorAll(".nw-header .nw-desktop-nav .nw-navlink"))'.
                '.map(a => a.textContent.trim());'
            )[0];
            $this->assertSame(
                ['Deals', 'Schedule', 'Partners', 'FAQ', 'Contact'],
                $labels,
                'The header must render the seeded menu, in the seeded desktop order.'
            );

            // Events is a dropdown trigger rather than a link, and must still be there.
            $this->assertSame(
                'Events',
                trim((string) $browser->script(
                    'const t = Array.from(document.querySelectorAll(".nw-desktop-nav .nw-dropdown-trigger"))'.
                    '.find(el => /Events/.test(el.textContent)); return t ? t.textContent.trim() : "";'
                )[0]),
                'The Events dropdown must render from its menu item.'
            );

            // The external NAVCO footer link keeps its new-tab + rel attributes.
            $navco = $browser->script(
                'const a = Array.from(document.querySelectorAll(".nw-footer a")).find(el => /NAVCO/.test(el.textContent)); '.
                'return a ? [a.getAttribute("target"), a.getAttribute("rel")] : null;'
            )[0];
            $this->assertSame(['_blank', 'noopener noreferrer'], $navco, 'The external NAVCO link must open in a new tab with rel set.');

            // Clicking a footer link navigates to its page.
            $browser->clickLink('Navy Ranks')
                ->assertPathIs('/navy-ranks/');
        });
    }

    public function test_the_rendered_nav_is_driven_by_the_database(): void
    {
        // Add a link to the seeded header menu, then confirm it appears on the page —
        // proof the nav renders from menu data, not a hardcoded array. Cleaned up after.
        // The Browser suite runs against the shared dev DB (no transaction), so a prior
        // run killed mid-test could leave the sentinel behind — clear any stray one first.
        MenuItem::query()->where('url', '/e2e-sentinel/')->delete();
        $header = Menu::query()->where('key', 'header-primary')->firstOrFail();
        $item = $header->items()->create([
            'label' => 'E2E Sentinel Link',
            'url' => '/e2e-sentinel/',
            'sort_order' => 999,
            'is_active' => true,
        ]);
        // Page responses are cached (spatie/laravel-responsecache); bust it so the
        // freshly-added link is rendered rather than a stale cached page.
        Artisan::call('responsecache:clear');

        try {
            $this->browse(function (Browser $browser): void {
                // Nav links are `text-transform: uppercase`, so the browser reports
                // the rendered (uppercased) text.
                $browser->visit('/veterans-day/free-meals/')
                    ->assertPresent('.nw-header .nw-desktop-nav')
                    ->assertSeeIn('.nw-header .nw-desktop-nav', 'E2E SENTINEL LINK');
            });
        } finally {
            $item->delete();
            Artisan::call('responsecache:clear');
        }
    }

    public function test_the_mobile_menu_toggles_the_nav(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->resize(390, 844)                            // mobile width
                ->visit('/veterans-day/free-meals/')
                ->assertPresent('.nw-hamburger')
                ->assertMissing('.nw-header .nw-mobile-panel')    // collapsed (display:none) by default
                ->click('.nw-hamburger')
                ->waitFor('.nw-header .nw-mobile-panel')
                ->assertVisible('.nw-header .nw-mobile-panel')    // revealed by the CSS-only toggle
                ->assertSeeIn('.nw-header .nw-mobile-panel', 'DEALS'); // nav links are uppercased via CSS
        });
    }
}
