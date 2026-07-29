@extends('layouts.base')

{{-- Discount-brand guide body. Rendered from the page's primary Offer (+ its
     tiers, redemption steps, FAQs, sources). NavyWeek is an independent publisher;
     the independence disclosure is emitted first, per the site's YMYL/E-E-A-T policy. --}}
@section('content')
    <main class="discount-guide">
        <p class="independence-disclosure" role="note">
            NavyWeek.org is an independent editorial publisher and is
            <strong>not affiliated</strong> with {{ $offer->connection?->brand ?? $page->title }} or the U.S. Navy.
            We may earn a commission from links on this page.
        </p>

        <article>
            <header class="guide-hero">
                <h1>{{ $page->title }}</h1>
                @if ($offer->headline_discount)
                    <p class="headline-discount">{{ $offer->headline_discount }}</p>
                @endif
                @if ($offer->discount_summary)
                    <p class="discount-summary">{{ $offer->discount_summary }}</p>
                @endif

                @if ($offer->official_url)
                    <p class="cta">
                        <a class="cta-primary" href="{{ $offer->official_url }}" rel="sponsored noopener noreferrer" target="_blank">
                            {{ $offer->cta_label ?? 'Get this discount' }}
                        </a>
                        @if ($offer->cta_subnote)
                            <span class="cta-subnote">{{ $offer->cta_subnote }}</span>
                        @endif
                    </p>
                @endif

                @if ($offer->verification)
                    <p class="verification">
                        Verification: {{ $offer->verification->label() }}@if ($offer->verification_url)
                            — <a href="{{ $offer->verification_url }}" rel="noopener noreferrer" target="_blank">verify eligibility</a>@endif
                    </p>
                @endif
            </header>

            @if (filled($offer->tiers))
                <section aria-labelledby="savings-heading" class="savings-tiers">
                    <h2 id="savings-heading">Savings by audience</h2>
                    <table>
                        <thead>
                            <tr><th scope="col">Who</th><th scope="col">Discount</th><th scope="col">Notes</th></tr>
                        </thead>
                        <tbody>
                            @foreach ($offer->tiers as $tier)
                                <tr>
                                    <th scope="row">{{ $tier->audience }}</th>
                                    <td>{{ $tier->amount }}</td>
                                    <td>{{ $tier->note }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </section>
            @endif

            @foreach (['eligibility' => 'Who is eligible', 'exclusions' => 'Exclusions', 'key_facts' => 'Key facts'] as $field => $label)
                @php($items = $offer->{$field})
                @if (filled($items))
                    <section aria-labelledby="{{ $field }}-heading">
                        <h2 id="{{ $field }}-heading">{{ $label }}</h2>
                        <ul>
                            @foreach ($items as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            @endforeach

            @php($online = $offer->redemptionSteps->where('channel', \App\Domain\Catalog\Enums\RedemptionChannel::Online))
            @php($inStore = $offer->redemptionSteps->where('channel', \App\Domain\Catalog\Enums\RedemptionChannel::InStore))
            @if ($online->isNotEmpty() || $inStore->isNotEmpty())
                <section aria-labelledby="redeem-heading" class="how-to-redeem">
                    <h2 id="redeem-heading">How to redeem</h2>
                    @foreach (['Online' => $online, 'In store' => $inStore] as $channelLabel => $steps)
                        @if ($steps->isNotEmpty())
                            <h3>{{ $channelLabel }}</h3>
                            <ol>
                                @foreach ($steps as $step)
                                    <li>
                                        <strong>{{ $step->title }}</strong>
                                        @if ($step->detail)
                                            <span>{{ $step->detail }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @endforeach
                </section>
            @endif

            @if ($offer->faqs->isNotEmpty())
                <section aria-labelledby="faq-heading" class="faqs">
                    <h2 id="faq-heading">Frequently asked questions</h2>
                    @foreach ($offer->faqs as $faq)
                        <details>
                            <summary>{{ $faq->question }}</summary>
                            <div>{{ $faq->answer }}</div>
                        </details>
                    @endforeach
                </section>
            @endif

            @if ($offer->sources->isNotEmpty())
                <section aria-labelledby="sources-heading" class="sources">
                    <h2 id="sources-heading">Sources</h2>
                    <ol>
                        @foreach ($offer->sources as $source)
                            <li>
                                <a href="{{ $source->url }}" rel="noopener noreferrer nofollow" target="_blank">{{ $source->label }}</a>
                                @if ($source->publisher)
                                    <span class="source-publisher">— {{ $source->publisher }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

            @if ($offer->official_url)
                <footer class="sticky-cta">
                    <a class="cta-primary" href="{{ $offer->official_url }}" rel="sponsored noopener noreferrer" target="_blank">
                        {{ $offer->sticky_cta_label ?? $offer->cta_label ?? 'Get this discount' }}
                    </a>
                </footer>
            @endif
        </article>
    </main>
@endsection
