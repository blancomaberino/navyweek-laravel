{{-- Site header — ported 1:1 from the legacy src/components/Header.tsx. CSS-only
     (no JS): every link is in the SSR HTML for crawlers and paints instantly; the
     mobile menu uses the checkbox-toggle pattern. The bar itself is MENU DATA
     ($navItems / $mobileNavItems — labels, urls, both orderings, slots and active
     slugs); the CONTENTS of its two panels are catalog data ($deals, $eventLinks).
     All shared by App\Domain\Navigation\View\NavigationComposer.

     Desktop and mobile order deliberately differ (the bar leads with Deals, the panel
     with Schedule), which is why menu_items carries `mobile_sort_order`. --}}
@php
    // The header renders the curated REGISTRY order (Header.tsx maps `discounts`
    // as-is); only the Deals section above the footer sorts by publish date.
    $deals ??= app(\App\Domain\Navigation\Support\ChromeCatalog::class)->menuDeals();
    $eventLinks ??= app(\App\Domain\Navigation\Support\ChromeCatalog::class)->eventLinks();
    $lastUpdated ??= config('site.last_updated');

    // The legacy header compares a nav item's SLUG to an `activePage` the page view
    // passes in — not the current path — so `/city/honolulu-hilo/` lights SCHEDULE
    // and `/discount/yeti-military-veteran/` lights DEALS. Resolved for us by
    // ChromeCatalog::activePage() and shared by NavigationComposer.
    $activePage ??= app(\App\Domain\Navigation\Support\ChromeCatalog::class)
        ->activePage(request()->getPathInfo());
    $isActive = static fn (string $slug): bool => $activePage !== null && $slug === $activePage;
    $eventActive = collect($eventLinks)->contains(static fn ($l) => $isActive($l['slug']));

    // The nav is menu data (NavigationComposer -> NavigationTree::header()), with the
    // defaults as the render-time fallback so the chrome never paints empty.
    $navItems ??= app(\App\Domain\Navigation\Support\NavigationTree::class)->header();
    $mobileNavItems ??= app(\App\Domain\Navigation\Support\NavigationTree::class)->headerMobile();

    // The CTA sits OUTSIDE the <nav> on desktop but inside the panel on mobile, so the
    // desktop bar iterates everything except it.
    $ctaItem = collect($navItems)->firstWhere('slot', \App\Domain\Navigation\Enums\MenuItemSlot::Cta);
    $barItems = collect($navItems)
        ->reject(fn (array $i): bool => $i['slot'] === \App\Domain\Navigation\Enums\MenuItemSlot::Cta)
        ->all();

    // The legacy derives each link's test id from its nav slug (link-discount,
    // link-schedule, link-partners…), falling back to the label for the anchor-only
    // items that have no slug.
    $testId = static fn (array $item): string => $item['activeSlug']
        ?: (\Illuminate\Support\Str::slug($item['label']) ?: 'item');

    $updatedAt = \Illuminate\Support\Carbon::parse($lastUpdated)->timezone('America/New_York');
    $updatedLabel = $updatedAt->format('F j, Y').' at '.$updatedAt->format('g:i A').' ET';

    $anchorSvg = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="5" r="3"/><line x1="12" y1="22" x2="12" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/></svg>';
    // lucide ChevronDown, 12px — the legacy nav uses the icon, not a text glyph.
    $chevronSvg = '<svg class="nw-dropdown-chevron" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>';
@endphp

<div class="nw-banner" role="status" aria-label="Site last updated {{ $updatedLabel }}">
    <strong>Last updated:</strong> {{ $updatedLabel }}
</div>

<header class="nw-header">
    <input type="checkbox" id="nw-mobile-toggle" class="nw-mob-check" aria-label="Toggle menu">

    <div class="nw-header-bar">
        <a href="/" class="nw-brand" data-testid="link-home">
            <span class="nw-brand-anchor" style="color: var(--gold)">{!! $anchorSvg !!}</span>
            <span class="nw-brand-name">NAVYWEEK</span><span class="nw-brand-tld">.ORG</span>
        </a>

        <nav aria-label="Main navigation" class="nw-desktop-nav">
            @foreach ($barItems as $item)
            @php $itemActive = $item['activeSlug'] !== null && $isActive($item['activeSlug']); @endphp
            @switch($item['slot'])

            {{-- Deals — mega-menu of every discount-brand guide --}}
            @case(\App\Domain\Navigation\Enums\MenuItemSlot::Deals)
            <div class="nw-dropdown nw-mega">
                <a href="{{ $item['href'] }}" @class(['nw-navlink', 'is-active' => $itemActive]) data-testid="link-{{ $testId($item) }}">{{ $item['label'] }}</a>
                <span class="nw-dropdown-trigger" aria-hidden="true">{!! $chevronSvg !!}</span>
                <div class="nw-mega-panel" role="menu">
                    <div class="nw-mega-inner">
                        <div class="nw-mega-grid">
                            @foreach ($deals as $deal)
                                <a href="{{ $deal['url'] }}" class="nw-mega-item">
                                    <span class="co">{{ $deal['brand'] }} discount</span>
                                </a>
                            @endforeach
                        </div>
                        <div class="nw-mega-foot">
                            <a href="{{ $item['href'] }}" class="nw-mega-all">View all deals &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
            @break

            {{-- Events — dropdown of the four hubs --}}
            @case(\App\Domain\Navigation\Enums\MenuItemSlot::Events)
            <div class="nw-dropdown">
                <span @class(['nw-dropdown-trigger', 'is-active' => $eventActive]) tabindex="0" role="button" aria-haspopup="true">{{ $item['label'] }}{!! $chevronSvg !!}</span>
                <div class="nw-dropdown-menu" role="menu">
                    @foreach ($eventLinks as $link)
                        <a href="{{ $link['href'] }}" class="nw-dropdown-event @if ($isActive($link['slug'])) is-active @endif">{{ $link['label'] }}</a>
                        @foreach ($link['children'] ?? [] as $child)
                            {{-- No active state on the sub-items: the legacy renders
                                 these with a bare class (Header.tsx). --}}
                            <a href="{{ $child['href'] }}" class="nw-dropdown-subevent">{{ $child['label'] }}</a>
                        @endforeach
                    @endforeach
                </div>
            </div>
            @break

            @default
            <a href="{{ $item['href'] }}" @if ($item['target']) target="{{ $item['target'] }}" @endif @if ($item['rel']) rel="{{ $item['rel'] }}" @endif @class(['nw-navlink', 'is-active' => $itemActive]) data-testid="link-{{ $testId($item) }}">{{ $item['label'] }}</a>
            @endswitch
            @endforeach
        </nav>

        @if ($ctaItem !== null)
            <a href="{{ $ctaItem['href'] }}" @if ($ctaItem['target']) target="{{ $ctaItem['target'] }}" @endif @if ($ctaItem['rel']) rel="{{ $ctaItem['rel'] }}" @endif class="nw-cta" data-testid="link-official-site">{{ $ctaItem['label'] }}</a>
        @endif

        <label for="nw-mobile-toggle" class="nw-hamburger" aria-label="Toggle menu" role="button">
            <span class="nw-menu">&#9776;</span>
            <span class="nw-x">&times;</span>
        </label>
    </div>

    <nav aria-label="Mobile navigation" class="nw-mobile-panel">
        @foreach ($mobileNavItems as $item)
        @php $itemActive = $item['activeSlug'] !== null && $isActive($item['activeSlug']); @endphp
        @switch($item['slot'])

        @case(\App\Domain\Navigation\Enums\MenuItemSlot::Events)
        <details class="nw-mob-acc">
            <summary @class(['nw-mob-acc-summary', 'is-active' => $eventActive])>
                <span>{{ $item['label'] }}</span>
                <span class="nw-mob-acc-chevron" aria-hidden="true"></span>
            </summary>
            <div class="nw-mob-acc-body">
                @foreach ($eventLinks as $link)
                    <a href="{{ $link['href'] }}" class="nw-mob-sublink @if ($isActive($link['slug'])) is-active @endif">{{ $link['label'] }}</a>
                    @foreach ($link['children'] ?? [] as $child)
                        <a href="{{ $child['href'] }}" class="nw-mob-subsublink">{{ $child['label'] }}</a>
                    @endforeach
                @endforeach
            </div>
        </details>
        @break

        @case(\App\Domain\Navigation\Enums\MenuItemSlot::Deals)
        <details class="nw-mob-acc">
            <summary @class(['nw-mob-acc-summary', 'is-active' => $itemActive])>
                <span>{{ $item['label'] }}</span>
                <span class="nw-mob-acc-chevron" aria-hidden="true"></span>
            </summary>
            <div class="nw-mob-acc-body">
                @foreach ($deals as $deal)
                    <a href="{{ $deal['url'] }}" class="nw-mob-sublink"><span>{{ $deal['brand'] }} discount</span></a>
                @endforeach
                <a href="{{ $item['href'] }}" class="nw-mob-acc-all">View all deals &rarr;</a>
            </div>
        </details>
        @break

        @case(\App\Domain\Navigation\Enums\MenuItemSlot::Cta)
        <a href="{{ $item['href'] }}" @if ($item['target']) target="{{ $item['target'] }}" @endif @if ($item['rel']) rel="{{ $item['rel'] }}" @endif class="nw-mob-cta">{{ $item['label'] }}</a>
        @break

        @default
        <a href="{{ $item['href'] }}" @if ($item['target']) target="{{ $item['target'] }}" @endif @if ($item['rel']) rel="{{ $item['rel'] }}" @endif @class(['nw-mob-link', 'is-active' => $itemActive])>{{ $item['label'] }}</a>
        @endswitch
        @endforeach
    </nav>
</header>
