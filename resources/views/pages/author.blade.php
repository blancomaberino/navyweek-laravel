@extends('layouts.base')

{{-- /authors/{slug}/ author profile. Head + JSON-LD (Person + Breadcrumb + ProfilePage +
     ItemList) locked by SeoHead + AuthorPageSchema. The profile is rendered from the byline
     User (the page's pageable), not a CMS body — the hub pattern. --}}
@php
    /** @var \App\Domain\Publishing\Models\Page $page */
    /** @var \App\Models\User $author */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Publishing\Models\Page> $authored */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Publishing\Models\Page> $reviewed */
@endphp

@section('content')
    <main class="author-page">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $author->name }}</span>
        </nav>

        <header class="author-hero">
            @if ($author->avatar_path)
                <img src="{{ $author->avatar_path }}" width="160" height="160" loading="eager"
                     alt="Portrait of {{ $author->name }}" class="author-avatar">
            @endif
            <div class="author-hero-body">
                <p class="eyebrow">// Author Profile</p>
                <h1>{{ $author->name }}</h1>
                @if ($author->job_title)
                    <p class="author-role">{{ $author->job_title }}</p>
                @endif
                @if ($author->credentials)
                    <p class="author-credentials">{{ $author->credentials }}</p>
                @endif
            </div>
        </header>

        @if ($author->bio)
            <section class="author-bio">
                <p class="lead">{{ $author->bio }}</p>
            </section>
        @endif

        @if ($author->knows_about)
            <section class="author-section" aria-labelledby="expertise-heading">
                <h2 id="expertise-heading">Areas of expertise</h2>
                <ul class="author-chips">
                    @foreach ($author->knows_about as $topic)
                        <li>{{ $topic }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($authored->isNotEmpty())
            <section class="author-section" aria-labelledby="writes-heading">
                <h2 id="writes-heading">Writes for NavyWeek.org</h2>
                <ul class="author-links">
                    @foreach ($authored as $article)
                        <li><a href="{{ $article->url_path }}">{{ $article->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($reviewed->isNotEmpty())
            <section class="author-section" aria-labelledby="reviews-heading">
                <h2 id="reviews-heading">Reviews for NavyWeek.org</h2>
                <p>{{ $author->name }} is a named expert reviewer on these NavyWeek.org guides:</p>
                <ul class="author-links">
                    @foreach ($reviewed as $article)
                        <li><a href="{{ $article->url_path }}">{{ $article->title }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($author->linkedin_url && \Illuminate\Support\Str::startsWith($author->linkedin_url, ['https://', 'http://']))
            <section class="author-section" aria-labelledby="connect-heading">
                <h2 id="connect-heading">Connect</h2>
                <ul class="author-links">
                    <li>
                        <a href="{{ $author->linkedin_url }}" target="_blank" rel="noopener noreferrer me">
                            LinkedIn
                        </a>
                    </li>
                </ul>
            </section>
        @endif

        <p class="author-disclosure">
            NavyWeek.org is an independent editorial publisher and is not affiliated with,
            endorsed by, or sponsored by the United States Navy or any brand mentioned.
        </p>
    </main>
@endsection
