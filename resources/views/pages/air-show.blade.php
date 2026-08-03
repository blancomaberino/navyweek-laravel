@extends('layouts.base')

{{-- Single air-show guide (/air-show/{slug}/). Head/JSON-LD (Article + WebPage +
     author/reviewer Person + FAQPage + Event) is byte-locked by SeoHead +
     AirShowPageSchema; this body is a clean semantic rebuild. --}}
@php
    /** @var \App\Domain\Pillars\Models\AirShow $show */
@endphp

@section('content')
    <main class="air-show">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/air-show/">Air Shows</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $show->short_name }}</span>
        </nav>

        <header class="air-show-hero">
            <p class="eyebrow">// {{ $show->city }}, {{ $show->state_name }} · {{ $show->admission->value }}</p>
            <h1>{{ $show->h1 }}</h1>
            @if ($show->hero_headline)
                <p class="hero-headline">{{ $show->hero_headline }}</p>
            @endif
            @foreach ($show->intro as $paragraph)
                <p class="intro">{{ $paragraph }}</p>
            @endforeach
        </header>

        @if (! empty($show->quick_facts))
            <section class="quick-facts" aria-label="Quick facts">
                <dl>
                    @foreach ($show->quick_facts as $fact)
                        <div><dt>{{ $fact['label'] ?? '' }}</dt><dd>{{ $fact['value'] ?? '' }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        @foreach ($show->sections as $section)
            <section class="air-show-section">
                @isset($section['heading'])<h2>{{ $section['heading'] }}</h2>@endisset
                @foreach ($section['paragraphs'] ?? [] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @if (! empty($section['bullets']))
                    <ul>
                        @foreach ($section['bullets'] as $bullet)
                            <li>{{ $bullet }}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        @if ($show->faqs->isNotEmpty())
            <section class="air-show-faqs" aria-label="Frequently asked questions">
                <h2>Frequently Asked Questions</h2>
                <dl>
                    @foreach ($show->faqs as $faq)
                        <dt>{{ $faq->question }}</dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($show->sources->isNotEmpty())
            <footer class="air-show-sources">
                <h2>Sources</h2>
                <ul>
                    @foreach ($show->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ $source->url }}" rel="noopener noreferrer" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </footer>
        @endif
    </main>
@endsection
