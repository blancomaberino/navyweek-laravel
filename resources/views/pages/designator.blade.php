@extends('layouts.base')

{{-- /navy-designators/{slug}/ — a single officer designator. A 1:1 port of the
     legacy detail renderer: NavyDesignatorsSlugRouter.tsx delegates a designator
     slug to src/page-views/NavyRankDetail.tsx, whose `isDesignator` branch is what
     this view reproduces section-for-section. The component's inline styles live
     as classes in resources/css/families/designators.css. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    $designatorRoot = PagePaths::root('designators');

    // `rankCategoryLabel()` for an officer-designator entry (src/lib/ranks/seo.ts).
    $categoryLabel = 'Officer Designator';

    // Legacy splits the overview / history prose on a blank line into <p>s
    // (NavyRankDetail.tsx: `rank.overview.split('\n\n')`).
    $paragraphs = static fn (?string $prose): array => array_values(array_filter(
        preg_split("/\n\n+/", (string) $prose) ?: [],
        static fn (string $p): bool => trim($p) !== '',
    ));

    // Lucide icons, copied path-for-path from the legacy render (lucide-react).
    $icon = static function (string $name, int $size, string $class = ''): string {
        $paths = [
            'arrow-right' => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
            'chevron-down' => '<path d="m6 9 6 6 6-6"></path>',
            'chevron-left' => '<path d="m15 18-6-6 6-6"></path>',
            'external-link' => '<path d="M15 3h6v6"></path><path d="M10 14 21 3"></path>'
                .'<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>',
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"'
            .' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            .' class="lucide lucide-'.$name.($class === '' ? '' : ' '.$class).'" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
    };

    $quickFacts = [
        ['label' => 'Designator', 'value' => $designator->designator_code],
        ['label' => 'Abbreviation', 'value' => $designator->abbreviation],
        ['label' => 'Community', 'value' => $designator->designator_community?->label()],
        ['label' => 'Paygrade Range', 'value' => $designator->paygrade],
        ['label' => 'NATO Range', 'value' => $designator->nato_code ?: '—'],
        ['label' => 'Category', 'value' => $categoryLabel],
    ];
@endphp

@section('content')
    <main class="dsg dsg-detail">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $designatorRoot }}">Officer Designators</a>
            @if ($designator->designator_community)
                <span aria-hidden="true">/</span>
                <a href="{{ PagePaths::child('designators', $designator->designator_community->value) }}">{{ $designator->designator_community->label() }}</a>
            @endif
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $designator->name }}</span>
        </nav>

        <header class="dsg-hero">
            <div>
                <div class="dsg-eyebrow">// {{ $categoryLabel }} &middot; {{ $designator->paygrade }}@if ($designator->nato_code) &middot; NATO {{ $designator->nato_code }}@endif</div>
                <h1 class="dsg-h1">{{ $page->h1 ?? $page->title }}</h1>
                <p class="dsg-hero-tagline">{{ $designator->hero_tagline }}</p>
            </div>
            <div class="dsg-insignia">
                <img src="{{ $designator->insignia_path }}" alt="{{ $designator->insignia_alt }}" width="120" height="120">
                <div class="dsg-insignia-label">Insignia</div>
            </div>
        </header>

        <section class="dsg-facts" aria-label="Quick facts">
            @foreach ($quickFacts as $fact)
                <div class="dsg-fact">
                    <div class="dsg-fact-label">{{ $fact['label'] }}</div>
                    <div class="dsg-fact-value">{{ $fact['value'] }}</div>
                </div>
            @endforeach
        </section>

        <section class="dsg-section" aria-label="Overview">
            <h2>OVERVIEW</h2>
            @foreach ($paragraphs($designator->overview) as $paragraph)
                <p class="dsg-prose">{{ $paragraph }}</p>
            @endforeach
        </section>

        <section class="dsg-section" aria-label="Responsibilities">
            <h2>RESPONSIBILITIES</h2>
            <ul class="dsg-responsibilities">
                @foreach ($designator->responsibilities as $responsibility)
                    <li>{{ $responsibility }}</li>
                @endforeach
            </ul>
        </section>

        <section class="dsg-section" aria-label="History">
            <h2>HISTORY</h2>
            @foreach ($paragraphs($designator->history) as $paragraph)
                <p class="dsg-prose">{{ $paragraph }}</p>
            @endforeach
        </section>

        <section class="dsg-section" aria-label="Commissioning sources">
            <h2>COMMISSIONING SOURCES</h2>
            <ul class="dsg-chips">
                @foreach ($designator->commissioning_sources ?? [] as $source)
                    <li>{{ $source }}</li>
                @endforeach
            </ul>
        </section>

        <section class="dsg-section" aria-label="Training pipeline">
            <h2>TRAINING PIPELINE</h2>
            <ol class="dsg-pipeline">
                @foreach ($designator->training_pipeline ?? [] as $stop)
                    <li>
                        <div class="dsg-pipeline-head">
                            <span class="dsg-pipeline-name">{{ $loop->iteration }}. {{ $stop['name'] ?? '' }}</span>
                            <span class="dsg-pipeline-duration">{{ $stop['duration'] ?? '' }}</span>
                        </div>
                        <div class="dsg-pipeline-location">{{ $stop['location'] ?? '' }}</div>
                        <div class="dsg-pipeline-desc">{{ $stop['description'] ?? '' }}</div>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="dsg-section" aria-label="Typical career path">
            <h2>TYPICAL CAREER PATH</h2>
            <ol class="dsg-career">
                @foreach ($designator->career_path ?? [] as $milestone)
                    <li>
                        <span class="dsg-career-paygrade">{{ $milestone['paygrade'] ?? '' }}</span>
                        <span>
                            <div class="dsg-career-title">{{ $milestone['title'] ?? '' }}</div>
                            <div class="dsg-career-desc">{{ $milestone['description'] ?? '' }}</div>
                        </span>
                    </li>
                @endforeach
            </ol>
        </section>

        @if ($relatedDesignators->isNotEmpty())
            <section class="dsg-section" aria-label="Related designators">
                <h2>RELATED DESIGNATORS</h2>
                <ul class="dsg-related dsg-related--designators">
                    @foreach ($relatedDesignators as $related)
                        <li>
                            <a href="{{ PagePaths::child('designators', $related->slug) }}">
                                <div class="dsg-related-meta">{{ $related->designator_code }} &middot; {{ $related->designator_community?->label() }}</div>
                                <div class="dsg-related-name">{{ $related->name }}</div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($relatedBases->isNotEmpty())
            <section class="dsg-section" aria-label="Related bases">
                <h2>RELATED BASES</h2>
                <ul class="dsg-related dsg-related--bases">
                    @foreach ($relatedBases as $base)
                        <li>
                            <a href="{{ PagePaths::child('bases', $base->slug) }}">
                                <div class="dsg-related-meta">Navy Base &middot; {{ $base->state_abbr }}</div>
                                <div class="dsg-related-name dsg-related-name--base">{{ $base->name }}</div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="dsg-panels" aria-label="Addressing and prerequisites">
            <div class="dsg-panel">
                <div class="dsg-panel-label">How to address</div>
                <div class="dsg-panel-body">{{ $designator->addressing }}</div>
            </div>
            <div class="dsg-panel">
                <div class="dsg-panel-label">Prerequisites</div>
                <ul class="dsg-panel-list">
                    @foreach ($designator->prerequisites as $prerequisite)
                        <li>{{ $prerequisite }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="dsg-panel">
                <div class="dsg-panel-label">Common assignments</div>
                <ul class="dsg-panel-list">
                    @foreach ($designator->common_assignments as $assignment)
                        <li>{{ $assignment }}</li>
                    @endforeach
                </ul>
            </div>
        </section>

        <section class="dsg-section" aria-label="Frequently asked questions">
            <h2>FREQUENTLY ASKED QUESTIONS</h2>
            <div class="dsg-faqs">
                @foreach ($designator->faqs as $faq)
                    <details class="nw-faq" @if ($loop->first) open @endif>
                        <summary>
                            <h3>{{ $faq->question }}</h3>
                            {!! $icon('chevron-down', 18, 'nw-faq-chev') !!}
                        </summary>
                        <div class="nw-faq-a">{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </div>
        </section>

        <section class="dsg-section dsg-sources" aria-label="Sources">
            <h2>SOURCES</h2>
            <ul>
                @foreach ($designator->sources as $source)
                    <li>
                        <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($source->url) }}" rel="noopener noreferrer" target="_blank">{{ $source->label }} {!! $icon('external-link', 11) !!}</a>
                    </li>
                @endforeach
            </ul>
            <div class="dsg-sources-updated">Last updated {{ $designator->last_updated?->format('Y-m-d') }}</div>
        </section>

        <div class="dsg-detail-foot">
            <a href="{{ $designatorRoot }}">{!! $icon('chevron-left', 14) !!} All Officer Designators</a>
            <a href="{{ PagePaths::root('ranks') }}">Full Rank Structure {!! $icon('arrow-right', 14) !!}</a>
        </div>
    </main>
@endsection
