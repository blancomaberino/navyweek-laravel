@extends('layouts.base')

{{-- /navy-designators/{slug}/ — a single officer designator, ported section-for-
     section from the legacy NavyDesignatorsSlugRouter detail view. --}}
@section('content')
    <main class="designator-detail">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('designators') }}">Navy Designators</a>
            @if ($designator->designator_community)
                <span aria-hidden="true">/</span>
                <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('designators', $designator->designator_community->value) }}">{{ $designator->designator_community->label() }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $designator->name }}</span>
        </nav>

        <header class="designator-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            @if ($designator->hero_tagline)
                <p class="intro">{{ $designator->hero_tagline }}</p>
            @endif
        </header>

        @include('partials.trust.key-facts')

        @foreach ([
            'OVERVIEW' => $designator->overview,
            'RESPONSIBILITIES' => $designator->responsibilities,
            'HISTORY' => $designator->history,
            'COMMISSIONING SOURCES' => $designator->commissioning_sources,
            'TRAINING PIPELINE' => $designator->training_pipeline,
            'TYPICAL CAREER PATH' => $designator->career_path,
        ] as $heading => $body)
            @continue(blank($body))
            <section class="designator-section" aria-label="{{ $heading }}">
                <h2>{{ $heading }}</h2>
                @if (is_array($body))
                    <ul>
                        @foreach ($body as $item)
                            <li>{{ is_array($item) ? ($item['text'] ?? $item['title'] ?? '') : $item }}</li>
                        @endforeach
                    </ul>
                @else
                    <p>{{ $body }}</p>
                @endif
            </section>
        @endforeach

        @if (filled($designator->related_designators))
            <section class="designator-section" aria-label="Related designators">
                <h2>RELATED DESIGNATORS</h2>
                <ul>
                    @foreach ($designator->related_designators as $related)
                        @php($relatedSlug = is_array($related) ? ($related['slug'] ?? null) : $related)
                        <li>
                            @if ($relatedSlug)
                                <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('designators', (string) $relatedSlug) }}">{{ is_array($related) ? ($related['name'] ?? $relatedSlug) : $related }}</a>
                            @else
                                {{ $related }}
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if (filled($designator->related_base_slugs))
            <section class="designator-section" aria-label="Related bases">
                <h2>RELATED BASES</h2>
                <ul>
                    @foreach ($designator->related_base_slugs as $baseSlug)
                        <li><a href="{{ \App\Domain\Publishing\Support\PagePaths::child('bases', (string) $baseSlug) }}">{{ \Illuminate\Support\Str::headline((string) $baseSlug) }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($designator->faqs->isNotEmpty())
            <section class="designator-section" aria-label="Frequently asked questions">
                <h2>FREQUENTLY ASKED QUESTIONS</h2>
                @foreach ($designator->faqs as $faq)
                    <details>
                        <summary><h3>{{ $faq->question }}</h3></summary>
                        <div>{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </section>
        @endif

        @if ($designator->sources->isNotEmpty())
            <section class="designator-section" aria-label="Sources">
                <h2>SOURCES</h2>
                <ol>
                    @foreach ($designator->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ $source->url }}" rel="noopener noreferrer" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </main>
@endsection
