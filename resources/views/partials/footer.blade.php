{{-- Site footer — ported 1:1 from the legacy src/components/Footer.tsx: the "Deals"
     section, then the brand row + four editable link columns ($footerGroups, shared
     by App\Domain\Navigation\View\NavigationComposer), the legal row ($legalNav), and
     the affiliation + last-updated bottom row. --}}
@php
    $footerGroups ??= \App\Domain\Navigation\Support\NavigationDefaults::footerGroups();
    $legalNav ??= \App\Domain\Navigation\Support\NavigationDefaults::legalItems();
    $lastUpdated ??= config('site.last_updated');
    $updatedLabel = \Illuminate\Support\Carbon::parse($lastUpdated)
        ->timezone('America/New_York')->format('F j, Y');
    $anchorSvg = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="5" r="3"/><line x1="12" y1="22" x2="12" y2="8"/><path d="M5 12H2a10 10 0 0 0 20 0h-3"/></svg>';
@endphp

@include('partials.deals-section')

<footer class="nw-footer">
    <div class="nw-footer-inner">
        <div class="nw-footer-brand">
            <span style="color: var(--gold)">{!! $anchorSvg !!}</span>
            <span class="nw-footer-brand-name">NAVYWEEK.ORG</span>
        </div>

        <div class="nw-footer-grid">
            @foreach ($footerGroups as $group)
                <div class="nw-footer-group">
                    <div class="nw-footer-group-heading">{{ $group['heading'] }}</div>
                    @foreach ($group['links'] as $link)
                        <a href="{{ $link['href'] }}"
                           @if (! empty($link['target'])) target="{{ $link['target'] }}" @endif
                           @if (! empty($link['rel'])) rel="{{ $link['rel'] }}" @endif>{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endforeach
        </div>

        <nav class="nw-footer-legal" aria-label="Legal">
            @foreach ($legalNav as $link)
                <a href="{{ $link['href'] }}"
                   @if (! empty($link['target'])) target="{{ $link['target'] }}" @endif
                   @if (! empty($link['rel'])) rel="{{ $link['rel'] }}" @endif>{{ $link['label'] }}</a>
            @endforeach
        </nav>

        <div class="nw-footer-meta">
            <span>Not affiliated with the U.S. Navy or NAVCO</span>
            <span>Last updated: {{ $updatedLabel }}</span>
        </div>
    </div>
</footer>
