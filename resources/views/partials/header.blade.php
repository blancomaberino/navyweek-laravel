{{-- Site header — CSS-only (no JS): every link is in the SSR HTML for crawlers and
     paints instantly; the mobile menu uses the checkbox-toggle pattern. --}}
@php
    $nav = [
        ['label' => 'Schedule', 'href' => '/schedule/'],
        ['label' => 'Navy Bases', 'href' => '/navy-bases/'],
        ['label' => 'Ranks', 'href' => '/navy-ranks/'],
        ['label' => 'Air Shows', 'href' => '/air-show/'],
        ['label' => 'Fleet Week', 'href' => '/fleetweek/'],
        ['label' => 'Discounts', 'href' => '/discount/'],
        ['label' => 'Veterans Day', 'href' => '/veterans-day/'],
    ];
    $path = '/'.trim(request()->getPathInfo(), '/').'/';
@endphp
<header class="nw-header">
    <input type="checkbox" id="nw-mob-toggle" class="nw-mob-check" aria-hidden="true">
    <div class="nw-header-bar">
        <a href="/" class="nw-brand"><span class="nw-brand-mark">◆</span> NAVYWEEK</a>
        <label for="nw-mob-toggle" class="nw-hamburger" aria-label="Toggle navigation menu" role="button">☰</label>
        <nav class="nw-nav" aria-label="Primary">
            @foreach ($nav as $item)
                <a href="{{ $item['href'] }}"
                   class="nw-navlink @if ($path === $item['href']) is-active @endif">{{ $item['label'] }}</a>
            @endforeach
        </nav>
        <a href="/schedule/" class="nw-header-cta">2026 Schedule</a>
    </div>
</header>
