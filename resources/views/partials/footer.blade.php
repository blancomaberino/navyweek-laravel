{{-- Site footer — editable nav columns + disclosure + legal row (ported from the
     legacy Footer). $footerGroups and $legalNav are shared by
     App\Domain\Navigation\View\NavigationComposer (with a hardcoded fallback in
     NavigationTree). The independent-publisher disclosure is fixed chrome. --}}
@php
    $footerGroups ??= \App\Domain\Navigation\Support\NavigationDefaults::footerGroups();
    $legalNav ??= \App\Domain\Navigation\Support\NavigationDefaults::legalItems();
@endphp
<footer class="nw-footer">
    <div class="nw-footer-inner">
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
        <p class="nw-footer-disclosure">
            NavyWeek.org is an independent, unofficial guide to the U.S. Navy Week program. It is not
            affiliated with, endorsed by, or sponsored by the United States Navy or the Navy Office of
            Community Outreach (NAVCO).
        </p>
        <nav class="nw-footer-legal" aria-label="Legal">
            @foreach ($legalNav as $link)
                <a href="{{ $link['href'] }}"
                   @if (! empty($link['target'])) target="{{ $link['target'] }}" @endif
                   @if (! empty($link['rel'])) rel="{{ $link['rel'] }}" @endif>{{ $link['label'] }}</a>
            @endforeach
        </nav>
    </div>
</footer>
