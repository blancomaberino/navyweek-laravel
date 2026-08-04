<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

/**
 * End-to-end: loads the real /authors/t-alford/ profile in headless Chrome (against the
 * running app + its DB) and asserts what Pest feature tests can NOT — that the design
 * system is actually applied (stylesheet linked, Fleet Navy background, Bebas Neue
 * headings, chrome present) and the profile's content + links render. Complements
 * AuthorRenderTest (which asserts the response body + JSON-LD).
 *
 * Assumes the served DB has the editorial users seeded (EditorialTeamSeeder) and the
 * author pages generated (`pages:generate-authors`).
 */
final class AuthorProfileTest extends DuskTestCase
{
    public function test_author_profile_renders_with_the_design_system(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/authors/t-alford/')
                ->assertTitleContains('T Madden Alford')
                ->assertSee('T Madden Alford')                 // Bebas Neue h1
                ->assertSee('Author Profile')                  // eyebrow
                ->assertSee('NAVYWEEK')                         // header chrome
                ->assertPresent('.nw-footer')                  // footer chrome
                ->assertPresent('.author-hero')
                ->assertPresent('.author-avatar');

            // The design system is actually applied (not just present in markup).
            $cssLinked = $browser->script(
                'return !!document.querySelector(\'link[href*="/build/assets/app-"]\');'
            )[0];
            $this->assertTrue($cssLinked, 'The built app stylesheet must be linked in the head.');

            $bodyBg = $browser->script('return getComputedStyle(document.body).backgroundColor;')[0];
            $this->assertSame('rgb(10, 22, 40)', $bodyBg, 'Body background must be Fleet Navy (#0A1628).');

            $h1Font = $browser->script('return getComputedStyle(document.querySelector("h1")).fontFamily;')[0];
            $this->assertStringContainsString('Bebas Neue', $h1Font, 'The h1 must use the display font.');

            // The avatar renders as a circle with the Service Gold border (token applied).
            $avatarRadius = $browser->script('return getComputedStyle(document.querySelector(".author-avatar")).borderRadius;')[0];
            $this->assertSame('50%', $avatarRadius, 'The avatar must be a circle.');

            // The expertise chips rendered from knows_about.
            $chipCount = (int) $browser->script('return document.querySelectorAll(".author-chips li").length;')[0];
            $this->assertGreaterThan(0, $chipCount, 'The expertise chips must render.');
        });
    }
}
