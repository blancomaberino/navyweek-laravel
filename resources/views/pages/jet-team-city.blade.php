@extends('layouts.base')

{{-- Jet-team city guide (/{team}/{slug}/). Head/JSON-LD (Article + WebPage + Person×2 +
     FAQPage + Event) byte-locked by SeoHead + JetTeamPageSchema::buildCity. --}}
@php
    /** @var \App\Domain\Pillars\Models\JetTeamCity $city */
    /** @var \App\Domain\Pillars\Models\JetTeam $team */
    $intro = is_array($city->intro) ? $city->intro : [];
    $sections = is_array($city->sections) ? $city->sections : [];
@endphp

@section('content')
    <main class="jet-team-city">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $team->base_path }}/">{{ $team->name }}</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $city->city }}</span>
        </nav>

        <header class="jet-team-hero">
            <p class="eyebrow">// {{ $team->name }} · {{ $city->admission->value }}</p>
            <h1>{{ $city->h1 }}</h1>
            <p class="dates">{{ $city->dates_label }}</p>
            @foreach ($intro as $paragraph)
                <p class="intro">{{ $paragraph }}</p>
            @endforeach
        </header>

        @include('partials.trust.key-facts', ['keyFacts' => filled($city->quick_facts) ? [
            'title' => trim(($city->team?->name ?? '').' '.$city->city.' '.$city->year).' — Key Facts',
            'facts' => $city->quick_facts,
        ] : null])

        @foreach ($sections as $section)
            <section class="jet-team-section">
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

        @if ($city->faqs->isNotEmpty())
            <section class="jet-team-faqs" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                <dl>
                    @foreach ($city->faqs as $faq)
                        <dt><h3>{{ $faq->question }}</h3></dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif

        @if ($city->sources->isNotEmpty())
            <footer class="jet-team-sources">
                <h2>SOURCES</h2>
                <ul>
                    @foreach ($city->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ $source->url }}" rel="noopener noreferrer nofollow" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </footer>
        @endif

        @if (filled($city->related_paragraph))
            <section class="nearby-related" aria-label="Nearby and related">
                <h2>NEARBY &amp; RELATED</h2>
                {{-- `related_paragraph` is either a plain string/list of strings or a
                     list of {before,label,href,after} link fragments. --}}
                @foreach ((array) $city->related_paragraph as $relatedPara)
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
