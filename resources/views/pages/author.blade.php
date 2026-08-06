@extends('layouts.base')

{{-- /authors/{slug}/ author profile. Head + JSON-LD (Person + Breadcrumb + ProfilePage +
     ItemList) locked by SeoHead + AuthorPageSchema. The profile is rendered from the byline
     User (the page's pageable), not a CMS body — the hub pattern. --}}
@php
    /** @var \App\Domain\Publishing\Models\Page $page */
    /** @var \App\Models\User $author */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Publishing\Models\Page> $authored */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Publishing\Models\Page> $reviewed */

    // Legacy uses "// Reviewer Profile" on the reviewer profile and "// Author Profile"
    // on the author profile; derive it from the person's own job title.
    $isReviewer = \Illuminate\Support\Str::contains(mb_strtolower((string) $author->job_title), 'reviewer');

    // Hero identity lines: the gold line is the person's SERVICE title, the grey line the
    // current civilian title. Both fall back to the compact byline pair when unset.
    $serviceLine = $author->service_title ?: $author->job_title;
    $currentLine = $author->current_title ?: $author->credentials;
    $location = collect([$author->location_city, $author->location_state])->filter()->implode(', ');

    // Career history: the structured timeline when it exists, else the legacy prose column.
    $histories = [
        ['id' => 'service-heading', 'label' => 'MILITARY SERVICE', 'entries' => $author->military_timeline, 'prose' => $author->military_service],
        ['id' => 'career-heading', 'label' => 'CIVILIAN CAREER', 'entries' => $author->civilian_timeline, 'prose' => $author->civilian_career],
    ];

    // A curated credit list wins over the auto-derived byline lists, which otherwise span
    // every generated page the default byline was back-filled onto.
    $curated = $author->featured_works ?? [];

    // The profile's own expertise list, falling back to the compact byline `knows_about`.
    $expertise = $author->profile_expertise ?: ($author->knows_about ?? []);

    $linkedinLabel = \Illuminate\Support\Str::of((string) $author->linkedin_url)
        ->replaceMatches('#^https?://(www\.)?#', '')
        ->rtrim('/')
        ->value();
@endphp

@section('content')
    <main class="author-page">
        <nav class="breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a><span aria-hidden="true">/</span><span aria-current="page">{{ $author->name }}</span></nav>

        <header class="author-hero">
            @if ($author->avatar_path)
                <img src="{{ $author->avatar_path }}" width="180" height="180" loading="eager"
                     alt="Portrait of {{ $author->name }}" class="author-avatar">
            @endif
            <div class="author-hero-body">
                <p class="eyebrow">// {{ $isReviewer ? 'Reviewer' : 'Author' }} Profile</p>
                <h1>{{ mb_strtoupper($author->name) }}</h1>
                @if ($serviceLine)
                    <p class="author-role">{{ $serviceLine }}</p>
                @endif
                @if ($currentLine)
                    <p class="author-credentials">{{ $currentLine }}</p>
                @endif
                @if ($location !== '')
                    <p class="author-location">{{ $location }}</p>
                @endif
            </div>
        </header>

        @if ($author->bio)
            <section class="author-bio">
                <p>{{ $author->bio }}</p>
            </section>
        @endif

        @foreach ($histories as $history)
            @continue(! $history['entries'] && ! $history['prose'])
            <section class="author-section" aria-labelledby="{{ $history['id'] }}">
                <h2 id="{{ $history['id'] }}">{{ $history['label'] }}</h2>
                @if ($history['entries'])
                    <ol class="author-timeline">
                        @foreach ($history['entries'] as $entry)
                            <li>
                                <span class="author-timeline-title">{{ $entry['title'] }}</span>
                                <span class="author-timeline-org">{{ $entry['org'] }}</span>
                                <span class="author-timeline-period">{{ $entry['period'] }}</span>
                                @if (($entry['detail'] ?? null) !== null)
                                    <p class="author-timeline-detail">{{ $entry['detail'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p>{{ $history['prose'] }}</p>
                @endif
            </section>
        @endforeach

        @if ($expertise !== [])
            <section class="author-section" aria-labelledby="expertise-heading">
                <h2 id="expertise-heading">AREAS OF EXPERTISE</h2>
                @if ($author->expertise_lead)
                    <p>{{ $author->expertise_lead }}</p>
                @endif
                <ul class="author-chips">
                    @foreach ($expertise as $topic)
                        <li>{{ $topic }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($curated !== [])
            <section class="author-section" aria-labelledby="works-heading">
                <h2 id="works-heading">{{ $isReviewer ? 'REVIEWS FOR NAVYWEEK.ORG' : 'WRITES FOR NAVYWEEK.ORG' }}</h2>
                @if ($author->works_lead)
                    <p>{{ $author->works_lead }}</p>
                @endif
                <ul class="author-links">
                    @foreach ($curated as $work)
                        {{-- No whitespace between the link and its note: the legacy JSX
                             strips the newline, so the gap is the note's 8px margin alone. --}}
                        <li>
                            <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($work['url']) }}">{{ $work['label'] }}</a>@if (($work['note'] ?? null) !== null)<span class="author-work-note">&mdash; {{ $work['note'] }}</span>@endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @else
            @if ($authored->isNotEmpty())
                <section class="author-section" aria-labelledby="writes-heading">
                    <h2 id="writes-heading">WRITES FOR NAVYWEEK.ORG</h2>
                    <ul class="author-links">
                        @foreach ($authored as $article)
                            <li><a href="{{ $article->url_path }}">{{ $article->title }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if ($reviewed->isNotEmpty())
                <section class="author-section" aria-labelledby="reviews-heading">
                    <h2 id="reviews-heading">REVIEWS FOR NAVYWEEK.ORG</h2>
                    <p>{{ $author->name }} is a named expert reviewer on these NavyWeek.org guides:</p>
                    <ul class="author-links">
                        @foreach ($reviewed as $article)
                            <li><a href="{{ $article->url_path }}">{{ $article->title }}</a></li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif

        @if ($author->linkedin_url && \Illuminate\Support\Str::startsWith($author->linkedin_url, ['https://', 'http://']))
            <section class="author-section" aria-labelledby="connect-heading">
                <h2 id="connect-heading">CONNECT</h2>
                <ul class="author-links">
                    <li>
                        <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($author->linkedin_url) }}" target="_blank" rel="noopener noreferrer me">
                            LinkedIn &mdash; {{ $linkedinLabel }}
                        </a>
                    </li>
                </ul>
            </section>
        @endif

        @if ($author->profile_reviewed_at)
            <p class="author-reviewed">Profile last reviewed: {{ $author->profile_reviewed_at->translatedFormat('F Y') }}</p>
        @endif
    </main>
@endsection
