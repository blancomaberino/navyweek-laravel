<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end: loads the real /veterans-day/free-meals/ page in a headless browser
 * (against the running app + its DB) and asserts the things Pest feature tests can
 * NOT — that the design system is actually applied (stylesheet linked, Fleet Navy
 * background, chrome present) and the client-side offers filter works. Complements
 * VeteransDayFreeMealsRenderTest (which asserts the response body + JSON-LD).
 */
final class VeteransDayFreeMealsTest extends DuskTestCase
{
    public function test_page_renders_with_the_design_system_and_a_working_filter(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/veterans-day/free-meals/')
                ->assertTitleContains('Veterans Day Free Meals 2026')
                ->assertSee('Veterans Day Free Meals 2026')   // Bebas Neue h1 (text is mixed-case)
                ->assertSee('NAVYWEEK')                        // header chrome
                ->assertPresent('.nw-footer')                  // footer chrome
                ->assertPresent('.vdm-table')
                ->assertSee('verified offers');

            // The design system is actually applied (not just present in markup):
            // the built stylesheet is linked and the Fleet Navy token is in effect.
            $cssLinked = $browser->script(
                'return !!document.querySelector(\'link[href*="/build/assets/app-"]\');'
            )[0];
            $this->assertTrue($cssLinked, 'The built app stylesheet must be linked in the head.');

            $bodyBg = $browser->script('return getComputedStyle(document.body).backgroundColor;')[0];
            $this->assertSame('rgb(10, 22, 40)', $bodyBg, 'Body background must be Fleet Navy (#0A1628).');

            $h1Font = $browser->script('return getComputedStyle(document.querySelector("h1")).fontFamily;')[0];
            $this->assertStringContainsString('Bebas Neue', $h1Font, 'The h1 must use the display font.');

            // The offers table rendered rows, each with a green "Verified" badge.
            $rowCount = (int) $browser->script(
                'return document.querySelectorAll(".vdm-table tbody tr").length;'
            )[0];
            $this->assertGreaterThan(0, $rowCount);
            $browser->assertPresent('.vdm-badge');

            // The client-side filter narrows the visible rows (progressive enhancement works).
            $browser->type('[data-testid=vdm-search]', 'applebee')->pause(400);
            $visible = (int) $browser->script(
                'return Array.from(document.querySelectorAll(".vdm-table tbody tr")).filter(r => !r.hidden).length;'
            )[0];
            $this->assertSame(1, $visible, 'Searching "applebee" should leave exactly one visible offer row.');
            $browser->assertSee('Showing 1 of');
        });
    }
}
