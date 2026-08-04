{{-- Site header — CSS-only (no JS): every link is in the SSR HTML for crawlers and
     paints instantly; the mobile menu uses the checkbox-toggle pattern.

     The primary nav is editable in the admin panel: $primaryNav is shared by
     App\Domain\Navigation\View\NavigationComposer (with a hardcoded fallback in
     NavigationTree). The brand mark and the "2026 Schedule" CTA are fixed chrome,
     not menu-managed. --}}
@php
    $primaryNav ??= \App\Domain\Navigation\Support\NavigationDefaults::headerItems();
    // Slash-insensitive match so an editor entering "/schedule" (no trailing slash)
    // still highlights on "/schedule/", and the homepage compares cleanly.
    $currentPath = trim(request()->getPathInfo(), '/');
    $isActive = fn (string $href): bool => trim($href, '/') === $currentPath;
@endphp
<header class="nw-header">
    <input type="checkbox" id="nw-mob-toggle" class="nw-mob-check" aria-hidden="true">
    <div class="nw-header-bar">
        <a href="/" class="nw-brand"><span class="nw-brand-mark">◆</span> NAVYWEEK</a>
        <label for="nw-mob-toggle" class="nw-hamburger" aria-label="Toggle navigation menu" role="button">☰</label>
        <nav class="nw-nav" aria-label="Primary">
            @foreach ($primaryNav as $item)
                @if (! empty($item['children']))
                    <div class="nw-nav-item nw-has-children">
                        <a href="{{ $item['href'] }}"
                           aria-haspopup="true"
                           @if (! empty($item['target'])) target="{{ $item['target'] }}" @endif
                           @if (! empty($item['rel'])) rel="{{ $item['rel'] }}" @endif
                           class="nw-navlink @if ($isActive($item['href'])) is-active @endif">{{ $item['label'] }}</a>
                        <div class="nw-dropdown">
                            @foreach ($item['children'] as $child)
                                <a href="{{ $child['href'] }}"
                                   @if (! empty($child['target'])) target="{{ $child['target'] }}" @endif
                                   @if (! empty($child['rel'])) rel="{{ $child['rel'] }}" @endif
                                   class="nw-dropdown-link @if ($isActive($child['href'])) is-active @endif">{{ $child['label'] }}</a>
                            @endforeach
                        </div>
                    </div>
                @else
                    <a href="{{ $item['href'] }}"
                       @if (! empty($item['target'])) target="{{ $item['target'] }}" @endif
                       @if (! empty($item['rel'])) rel="{{ $item['rel'] }}" @endif
                       class="nw-navlink @if ($isActive($item['href'])) is-active @endif">{{ $item['label'] }}</a>
                @endif
            @endforeach
        </nav>
        <a href="/schedule/" class="nw-header-cta">2026 Schedule</a>
    </div>
</header>
