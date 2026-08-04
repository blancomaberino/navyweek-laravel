{{-- "Deals" brand grid — ported 1:1 from the legacy src/components/DealsSection.tsx.
     Rendered above the footer on every page; the deal list ($deals) is shared by
     App\Domain\Navigation\View\NavigationComposer, newest published first. --}}
@php
    $deals ??= app(\App\Domain\Navigation\Support\ChromeCatalog::class)->deals();
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
                            <span class="deals-card-logo">
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
                        <span class="deals-card-cta">{{ $deal['brand'] }} military discount &rarr;</span>
                    </a>
                @endforeach
            </div>

            <div class="deals-foot">
                <a href="/discount/" class="deals-all">View All Deals &rarr;</a>
            </div>
        </div>
    </section>
@endif
