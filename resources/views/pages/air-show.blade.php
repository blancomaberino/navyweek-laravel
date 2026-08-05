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

        @include('partials.trust.key-facts', ['keyFacts' => filled($show->quick_facts) ? [
            'title' => $show->name.' '.$show->year.' — Key Facts',
            'facts' => $show->quick_facts,
        ] : null])

        @foreach ($show->sections as $section)
            <section class="air-show-section">
                @isset($section['heading'])<h2>{{ mb_strtoupper($section['heading']) }}</h2>@endisset
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
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <dl>
                    @foreach ($show->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($show->sources->isNotEmpty())
            <footer class="air-show-sources">
                <h2>SOURCES</h2>
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

        @if (filled($show->related_paragraph))
            <section class="nearby-related" aria-label="Nearby and related">
                <h2>NEARBY &amp; RELATED</h2>
                {{-- `related_paragraph` is either a plain string/list of strings or a
                     list of {before,label,href,after} link fragments. --}}
                @foreach ((array) $show->related_paragraph as $relatedPara)
                    @if (is_array($relatedPara))
                        <p>{{ $relatedPara['before'] ?? '' }}@if (! empty($relatedPara['href']))<a href="{{ $relatedPara['href'] }}">{{ $relatedPara['label'] ?? $relatedPara['href'] }}</a>@endif{{ $relatedPara['after'] ?? '' }}</p>
                    @else
                        <p>{{ $relatedPara }}</p>
                    @endif
                @endforeach
            </section>
        @endif

        @include('partials.trust.editorial-policy')
    </main>
@endsection
