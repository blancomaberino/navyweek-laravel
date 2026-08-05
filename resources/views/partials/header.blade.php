{{-- Site header — ported 1:1 from the legacy src/components/Header.tsx. CSS-only
     (no JS): every link is in the SSR HTML for crawlers and paints instantly; the
     mobile menu uses the checkbox-toggle pattern. The top bar is fixed chrome; only
     the Deals mega-menu ($deals) and the Events dropdown ($eventLinks) are
     data-driven, shared by App\Domain\Navigation\View\NavigationComposer. --}}
@php
    $deals ??= app(\App\Domain\Navigation\Support\ChromeCatalog::class)->deals();
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
            {{-- Deals — mega-menu of every discount-brand guide --}}
            <div class="nw-dropdown nw-mega">
                <a href="/discount/" class="nw-navlink @if ($isActive('discount')) is-active @endif" data-testid="link-discount">Deals</a>
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
                            <a href="/discount/" class="nw-mega-all">View all deals &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>

            <a href="/schedule/" class="nw-navlink @if ($isActive('schedule')) is-active @endif" data-testid="link-schedule">Schedule</a>

            {{-- Events — dropdown of the four hubs --}}
            <div class="nw-dropdown">
                <span class="nw-dropdown-trigger @if ($eventActive) is-active @endif" tabindex="0" role="button" aria-haspopup="true">Events{!! $chevronSvg !!}</span>
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

            <a href="/#partners" class="nw-navlink" data-testid="link-partners">Partners</a>
            <a href="/#faq" class="nw-navlink" data-testid="link-faq">FAQ</a>
            <a href="/contact/" class="nw-navlink @if ($isActive('contact')) is-active @endif" data-testid="link-contact">Contact</a>
        </nav>

        <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer" class="nw-cta" data-testid="link-official-site">Official NAVCO Site</a>

        <label for="nw-mobile-toggle" class="nw-hamburger" aria-label="Toggle menu" role="button">
            <span class="nw-menu">&#9776;</span>
            <span class="nw-x">&times;</span>
        </label>
    </div>

    <nav aria-label="Mobile navigation" class="nw-mobile-panel">
        <a href="/schedule/" class="nw-mob-link @if ($isActive('schedule')) is-active @endif">Schedule</a>

        <details class="nw-mob-acc">
            <summary class="nw-mob-acc-summary @if ($eventActive) is-active @endif">
                <span>Events</span>
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

        <details class="nw-mob-acc">
            <summary class="nw-mob-acc-summary @if ($isActive('discount')) is-active @endif">
                <span>Deals</span>
                <span class="nw-mob-acc-chevron" aria-hidden="true"></span>
            </summary>
            <div class="nw-mob-acc-body">
                @foreach ($deals as $deal)
                    <a href="{{ $deal['url'] }}" class="nw-mob-sublink"><span>{{ $deal['brand'] }} discount</span></a>
                @endforeach
                <a href="/discount/" class="nw-mob-acc-all">View all deals &rarr;</a>
            </div>
        </details>

        <a href="/#partners" class="nw-mob-link">Partners</a>
        <a href="/#faq" class="nw-mob-link">FAQ</a>
        <a href="/contact/" class="nw-mob-link @if ($isActive('contact')) is-active @endif">Contact</a>
        <a href="https://outreach.navy.mil/Navy-Weeks/" target="_blank" rel="noopener noreferrer" class="nw-mob-cta">Official NAVCO Site</a>
    </nav>
</header>
