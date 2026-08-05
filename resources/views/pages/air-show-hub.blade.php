@extends('layouts.base')

{{-- Air-show hub (/air-show/). The published-show directory + key facts + about +
     FAQs. Head/JSON-LD (Article + ItemList + FAQPage) is byte-locked by SeoHead +
     AirShowPageSchema::buildHub. --}}
@php
    /** @var \App\Domain\Pillars\Models\AirShowHubMeta $hub */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\AirShow> $shows */
@endphp

@section('content')
    <main class="air-show-hub">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Air Shows</span>
        </nav>

        <header class="hub-hero">
            @if ($hub->eyebrow)
                <p class="eyebrow">// {{ $hub->eyebrow }}</p>
            @endif
            <h1>{{ $hub->hub_title }}</h1>
            @if ($hub->hub_subtitle)
                <p class="subtitle">{{ $hub->hub_subtitle }}</p>
            @endif
            @foreach ($hub->intro as $paragraph)
                <p class="intro">{{ $paragraph }}</p>
            @endforeach
        </header>

        @if (! empty($hub->key_facts))
            <section class="hub-key-facts" aria-label="Key facts">
                <dl>
                    @foreach ($hub->key_facts as $fact)
                        <div><dt>{{ $fact['label'] ?? '' }}</dt><dd>{{ $fact['value'] ?? '' }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="hub-shows" aria-label="Air shows">
            <h2>{{ $hub->seo_headline }} <span class="count">({{ $shows->count() }})</span></h2>
            @if ($shows->isEmpty())
                <p class="empty-state">Air-show guides are coming soon.</p>
            @else
                <ul class="show-list">
                    @foreach ($shows as $show)
                        <li class="show-card">
                            <a href="/air-show/{{ $show->slug }}/">
                                <span class="show-name">{{ $show->name }} {{ $show->year }}</span>
                                <span class="show-loc">{{ $show->city }}, {{ $show->state_name }}</span>
                                @if ($show->dates_label)
                                    <span class="show-dates">{{ $show->dates_label }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @foreach ($hub->about as $paragraph)
            <section class="hub-about"><p>{{ $paragraph }}</p></section>
        @endforeach

        @if ($hub->faqs->isNotEmpty())
            <section class="hub-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <dl>
                    @foreach ($hub->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif
        @include('partials.trust.editorial-policy')
    </main>
@endsection
