{{-- "Deals" brand grid — ported 1:1 from the legacy src/components/DealsSection.tsx.
     Rendered above the footer on every page; the deal list ($deals) is shared by
     App\Domain\Navigation\View\NavigationComposer, newest published first. --}}
@php
    $deals ??= app(\App\Domain\Navigation\Support\ChromeCatalog::class)->deals();

    // lucide ArrowRight — the legacy renders the icon, not a "→" glyph. It matters
    // for layout as well as looks: as a flex item it reserves its own width, so a
    // long brand name wraps a line earlier than a trailing character would.
    $arrow = static fn (int $size): string => '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
@endphp
@if (! empty($deals))
    <section class="deals-section" id="deals" aria-label="Military and veteran deals">
        <div class="deals-inner">
            <div class="deals-head">
                <p class="deals-eyebrow">// Military &amp; Veteran Savings</p>
                <h2 class="deals-title">DEALS</h2>
                <p class="deals-sub">Verified military and veteran discounts from major brands — each guide covers who qualifies, how to verify your service for free, and how to redeem.</p>
            </div>

            <div class="deals-grid">
                @foreach ($deals as $deal)
                    <a href="{{ $deal['url'] }}" class="deals-card">
                        @if (! empty($deal['logo']))
                            <span class="deals-card-logo" @if (! empty($deal['logoBackground'])) style="background: {{ $deal['logoBackground'] }}" @endif>
                                <img src="{{ $deal['logo'] }}" alt="{{ $deal['brand'] }} logo" loading="lazy">
                            </span>
                        @endif
                        @if (! empty($deal['headline']))
                            <span class="deals-card-headline">{{ $deal['headline'] }}</span>
                        @endif
                        <span>
                            <span class="deals-card-company">{{ $deal['brand'] }}</span>
                            @if (! empty($deal['category']))
                                <span class="deals-card-category">{{ $deal['category'] }}</span>
                            @endif
                        </span>
                        <span class="deals-card-cta">{{ $deal['brand'] }} military discount {!! $arrow(13) !!}</span>
                    </a>
                @endforeach
            </div>

            <div class="deals-foot">
                <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('discounts') }}" class="deals-all">View All Deals {!! $arrow(14) !!}</a>
            </div>
        </div>
    </section>
@endif
