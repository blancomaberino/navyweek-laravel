<?php

declare(strict_types=1);

use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Models\MenuItem;
use App\Domain\Navigation\Repositories\MenuRepositoryInterface;
use App\Domain\Navigation\Support\NavigationDefaults;
use App\Domain\Navigation\Support\NavigationTree;
use Database\Seeders\NavigationSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/*
|--------------------------------------------------------------------------
| Repository
|--------------------------------------------------------------------------
*/

it('returns active menus for a location ordered, with active top-level items and their children', function () {
    $footerA = Menu::factory()->location(MenuLocation::Footer)->create(['sort_order' => 1, 'key' => 'f-a']);
    $footerB = Menu::factory()->location(MenuLocation::Footer)->create(['sort_order' => 0, 'key' => 'f-b']);
    Menu::factory()->location(MenuLocation::Header)->create(['key' => 'h']);

    $parent = MenuItem::factory()->for($footerB)->create(['label' => 'Parent', 'sort_order' => 0]);
    MenuItem::factory()->for($footerB)->childOf($parent)->create(['label' => 'Child', 'sort_order' => 0]);
    MenuItem::factory()->for($footerB)->inactive()->create(['label' => 'Hidden item', 'sort_order' => 1]);
    MenuItem::factory()->for($footerA)->create(['label' => 'A link']);

    $menus = app(MenuRepositoryInterface::class)->activeMenusForLocation(MenuLocation::Footer);

    // Only footer menus, ordered by the menu sort_order (B before A).
    expect($menus->pluck('key')->all())->toBe(['f-b', 'f-a']);

    // Top-level items only (the inactive one is excluded), with children nested.
    $topLevel = $menus->firstWhere('key', 'f-b')->activeItems;
    expect($topLevel)->toHaveCount(1)
        ->and($topLevel->first()->label)->toBe('Parent')
        ->and($topLevel->first()->activeChildren)->toHaveCount(1)
        ->and($topLevel->first()->activeChildren->first()->label)->toBe('Child');
});

it('excludes inactive menus from a location', function () {
    Menu::factory()->location(MenuLocation::Legal)->inactive()->create(['key' => 'off']);

    $menus = app(MenuRepositoryInterface::class)->activeMenusForLocation(MenuLocation::Legal);

    expect($menus)->toBeEmpty();
});

/*
|--------------------------------------------------------------------------
| View-model tree (+ fallback)
|--------------------------------------------------------------------------
*/

it('falls back to the hardcoded defaults when no menus are seeded', function () {
    $tree = app(NavigationTree::class);

    expect($tree->header())->toBe(NavigationDefaults::headerItems())
        ->and($tree->footerGroups())->toBe(NavigationDefaults::footerGroups())
        ->and($tree->legal())->toBe(NavigationDefaults::legalItems());
});

it('builds the tree from seeded menus, preserving external target/rel and nesting', function () {
    (new NavigationSeeder)->run();

    $tree = app(NavigationTree::class);

    expect($tree->header())->toHaveCount(7)
        ->and($tree->footerGroups())->toHaveCount(4)
        ->and($tree->legal())->toHaveCount(3);

    // The one external footer link keeps its target/rel.
    $navco = collect($tree->footerGroups()[0]['links'])->firstWhere('label', 'Official NAVCO Site');
    expect($navco['target'])->toBe('_blank')
        ->and($navco['rel'])->toBe('noopener noreferrer')
        ->and($navco['href'])->toBe('https://outreach.navy.mil/Navy-Weeks/');
});

/*
|--------------------------------------------------------------------------
| Rendering (the partials render from menu data via the view composer)
|--------------------------------------------------------------------------
*/

it('renders the fixed header chrome ported from the legacy Header', function () {
    // The header top bar is FIXED chrome (Deals / Schedule / Events / Partners / FAQ /
    // Contact + the NAVCO button), matching the live site 1:1 — only the Deals
    // mega-menu and the Events dropdown are data-driven. It is deliberately NOT
    // menu-editable; the footer columns are.
    $html = view('partials.header')->render();

    expect($html)->toContain('aria-label="Main navigation"')
        ->and($html)->toContain('>Deals<')
        ->and($html)->toContain('>Schedule<')
        ->and($html)->toContain('>Events<')
        ->and($html)->toContain('>Partners<')
        ->and($html)->toContain('>FAQ<')
        ->and($html)->toContain('>Contact<')
        ->and($html)->toContain('Official NAVCO Site')
        ->and($html)->toContain('href="/air-show/"')          // Events dropdown
        ->and($html)->toContain('href="/thunderbirds/"');
});

it('renders footer columns and the legal row from seeded menu data', function () {
    (new NavigationSeeder)->run();

    $html = view('partials.footer')->render();

    expect($html)->toContain('Navy Reference')                       // a column heading
        ->and($html)->toContain('href="/navy-ratings/"')             // a column link
        ->and($html)->toContain('target="_blank"')                   // NAVCO external link
        ->and($html)->toContain('rel="noopener noreferrer"')
        ->and($html)->toContain('href="/privacy/"')                  // legal row
        ->and($html)->toContain('aria-label="Legal"');
});

it('reflects an editor renaming a footer link', function () {
    (new NavigationSeeder)->run();
    Menu::query()->where('key', 'footer-navy-reference')->firstOrFail()
        ->items()->where('label', 'Navy Ratings')->update(['label' => 'Enlisted Ratings']);

    $html = view('partials.footer')->render();

    expect($html)->toContain('Enlisted Ratings')
        ->and($html)->not->toContain('>Navy Ratings<');
});

it('renders the header chrome even with no menus seeded', function () {
    // The header owns no menu data, so an empty menus table must not blank the nav.
    $html = view('partials.header')->render();

    expect($html)->toContain('href="/schedule/"')
        ->and($html)->toContain('Official NAVCO Site');
});

it('marks the current-page link active, trailing-slash-insensitively', function () {
    (new NavigationSeeder)->run();

    // Both the canonical and slash-stripped path highlight the /discount/ link.
    foreach (['/discount/', '/discount'] as $path) {
        app()->instance('request', Request::create($path));

        expect(view('partials.header')->render())
            ->toMatch('~href="/discount/"[^>]*\bis-active\b~');
    }

    // A path that matches no link highlights nothing.
    app()->instance('request', Request::create('/no-such-page/'));
    expect(view('partials.header')->render())->not->toContain('is-active');
});

/*
|--------------------------------------------------------------------------
| Link safety (scheme allowlist + new-tab rel)
|--------------------------------------------------------------------------
*/

it('neutralizes a disallowed url scheme at render', function () {
    $menu = Menu::factory()->location(MenuLocation::Header)->create(['key' => 'header-primary']);
    MenuItem::factory()->for($menu)->create(['label' => 'Evil', 'url' => 'javascript:alert(document.cookie)']);
    MenuItem::factory()->for($menu)->create(['label' => 'Safe', 'url' => '/safe/']);

    $items = collect(app(NavigationTree::class)->header());

    expect($items->firstWhere('label', 'Evil')['href'])->toBe('#')
        ->and($items->firstWhere('label', 'Safe')['href'])->toBe('/safe/');

    // And the dangerous scheme never reaches the rendered HTML.
    expect(view('partials.header')->render())->not->toContain('javascript:');
});

it('defaults rel to noopener noreferrer for a new-tab link missing one', function () {
    $menu = Menu::factory()->location(MenuLocation::Header)->create(['key' => 'header-primary']);
    MenuItem::factory()->for($menu)->create([
        'label' => 'External', 'url' => 'https://example.com', 'target' => '_blank', 'rel' => null,
    ]);

    $item = collect(app(NavigationTree::class)->header())->firstWhere('label', 'External');

    expect($item['target'])->toBe('_blank')
        ->and($item['rel'])->toBe('noopener noreferrer');
});

it('reads the repository once per region despite repeated composer calls', function () {
    // Two partials share one request-scoped NavigationTree; each region must memoize.
    $repo = new class implements MenuRepositoryInterface
    {
        public int $calls = 0;

        public function activeMenusForLocation(MenuLocation $location): Collection
        {
            $this->calls++;

            return collect();
        }
    };
    $tree = new NavigationTree($repo);

    $tree->header();
    $tree->header();
    // Both header ORDERINGS share the one read — without that, the desktop bar and the
    // mobile panel each queried the menu and this guard could not see it, because it
    // never called headerMobile().
    $tree->headerMobile();
    $tree->headerMobile();
    $tree->footerGroups();
    $tree->footerGroups();
    $tree->legal();
    $tree->legal();

    expect($repo->calls)->toBe(3); // one read each for header, footer, legal
});

/*
|--------------------------------------------------------------------------
| Seeder
|--------------------------------------------------------------------------
*/

it('seeds the exact default structure and is idempotent', function () {
    (new NavigationSeeder)->run();
    (new NavigationSeeder)->run(); // second run must not duplicate

    expect(Menu::query()->count())->toBe(6)
        ->and(MenuItem::query()->count())->toBe(31);

    $header = Menu::query()->where('key', 'header-primary')->firstOrFail();
    expect($header->location)->toBe(MenuLocation::Header)
        ->and($header->items()->count())->toBe(7);
});
