<?php

declare(strict_types=1);

use App\Domain\Navigation\Enums\MenuItemSlot;
use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Support\NavigationTree;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use Database\Seeders\NavigationSeeder;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;

/**
 * The header used to be hardcoded while a `header-primary` menu sat in the CMS driving
 * nothing — editing it changed no pixel on the site. These assert the wire is real: what
 * the menu says is what the bar renders, in both orderings.
 */

/**
 * The header is chrome, so any 200 page exercises it — but the catch-all 301s a path
 * with no `pages` row, so the test has to supply one. Built by hand rather than via
 * `$this->get()`: the test client trims the trailing slash and would only ever hit the
 * slash-normalising redirect.
 */
function fetchChromePage(string $path = '/privacy/'): TestResponse
{
    Page::query()->firstOrCreate(
        ['url_path' => $path],
        [
            'slug' => trim($path, '/'),
            'page_type' => PageType::Static,
            'title' => 'Privacy Policy',
            'is_published' => true,
        ],
    );

    return TestResponse::fromBaseResponse(
        app(Kernel::class)->handle(Request::create("http://localhost{$path}"))
    );
}

/** Just the desktop bar — "Navy Bases" is a legitimate FOOTER link, so a whole-page
 *  assertion would be checking the wrong region. */
function desktopNav(string $html): string
{
    preg_match('/<nav aria-label="Main navigation".*?<\/nav>/s', $html, $m);

    expect($m)->not->toBeEmpty('The desktop nav did not render at all.');

    return $m[0];
}

function headerMenu(): Menu
{
    return Menu::query()->where('location', MenuLocation::Header)->sole();
}

it('renders the seeded header, not the placeholder that used to sit there', function () {
    $this->seed(NavigationSeeder::class);

    $page = fetchChromePage()->assertOk()->getContent();
    $nav = desktopNav($page);

    foreach (['Deals', 'Schedule', 'Events', 'Partners', 'FAQ', 'Contact'] as $label) {
        expect($nav)->toContain(">{$label}<");
    }

    // The CTA sits outside the <nav>, alongside it.
    expect($page)->toContain('Official NAVCO Site');

    // The placeholder links that never rendered must not come back into the bar.
    // ("Fleet Week" is deliberately not asserted absent — "Fleet Week Hub" is a real
    // entry INSIDE the Events dropdown, which comes from the catalog, not the menu.)
    expect($nav)->not->toContain('Navy Bases')
        ->and($nav)->not->toContain('>Ranks<')
        ->and($nav)->not->toContain('>Veterans Day<');
});

it('renders a label an editor changes in the menu', function () {
    $this->seed(NavigationSeeder::class);

    headerMenu()->items()->where('url', '/contact/')->update(['label' => 'Talk To Us']);

    expect(fetchChromePage()->getContent())->toContain('Talk To Us');
});

it('reorders the desktop bar when an editor reorders the menu', function () {
    $this->seed(NavigationSeeder::class);
    $menu = headerMenu();

    $before = desktopNav(fetchChromePage()->getContent());
    expect(strpos($before, '>Deals<'))->toBeLessThan(strpos($before, '>Contact<'));

    // Move the Deals mega-menu to the end of the bar. This is the assertion the old
    // hardcoded header could never satisfy: the PANEL moves, not just a link.
    $menu->items()->where('slot', MenuItemSlot::Deals)->update(['sort_order' => 99]);
    app()->forgetInstance(NavigationTree::class);

    $after = desktopNav(fetchChromePage()->getContent());
    expect(strpos($after, '>Deals<'))->toBeGreaterThan(strpos($after, '>Contact<'));
});

it('orders the mobile panel independently of the desktop bar', function () {
    $this->seed(NavigationSeeder::class);

    $tree = app(NavigationTree::class);

    $desktop = array_column($tree->header(), 'label');
    $mobile = array_column($tree->headerMobile(), 'label');

    // The legacy panel leads with Schedule where the bar leads with Deals.
    expect($desktop[0])->toBe('Deals')
        ->and($mobile[0])->toBe('Schedule')
        ->and($mobile[2])->toBe('Deals')
        // Same items either way — only the order differs.
        ->and(collect($mobile)->sort()->values()->all())
        ->toBe(collect($desktop)->sort()->values()->all());
});

it('falls back to the defaults when there is no header menu', function () {
    // No seeding at all: the chrome must never paint an empty bar.
    $html = fetchChromePage()->assertOk()->getContent();

    expect(desktopNav($html))->toContain('>Schedule<')
        ->and($html)->toContain('Official NAVCO Site');
});

it('hides an item an editor deactivates', function () {
    $this->seed(NavigationSeeder::class);

    headerMenu()->items()->where('url', '/#faq')->update(['is_active' => false]);

    expect(desktopNav(fetchChromePage()->getContent()))->not->toContain('>FAQ<');
});

it('sanitises a header link the same way the footer does', function () {
    // The header row is built by a DIFFERENT code path from the footer's, and the
    // first version of it hand-rolled the array — silently dropping LinkUrl::sanitize()
    // and the new-tab `rel` default. Both paths must go through mapItem().
    $this->seed(NavigationSeeder::class);

    headerMenu()->items()->where('url', '/contact/')->update([
        'url' => 'javascript:alert(document.cookie)',
        'target' => '_blank',
        'rel' => null,
    ]);

    $item = collect(app(NavigationTree::class)->header())->firstWhere('label', 'Contact');

    expect($item['href'])->toBe('#')
        ->and($item['rel'])->toBe('noopener noreferrer');

    expect(fetchChromePage()->getContent())->not->toContain('javascript:');
});

it('lights the family tab from a DETAIL page, not just an exact path match', function () {
    $this->seed(NavigationSeeder::class);

    // This is the whole reason `active_slug` is stored rather than derived from `url`:
    // /discount/{brand}/ must light DEALS, whose url is /discount/. A path match would
    // light nothing here, and the previous version of this test fetched /schedule/ —
    // where the slug and the path coincide — so it proved nothing.
    $html = fetchChromePage('/discount/yeti-military-veteran/')->getContent();

    expect($html)->toContain('class="nw-navlink is-active" data-testid="link-discount"');
});
