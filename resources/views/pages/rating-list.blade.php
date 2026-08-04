@extends('layouts.base')

{{-- /navy-ratings/ — every enlisted rating on one page: active ratings grouped by
     community (community anchors) + a historic section. Each row anchored by slug
     for /navy-ratings/#<slug>. Head/JSON-LD byte-locked by SeoHead + RankListSchema. --}}
@php
    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank>> $activeByCommunity */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank> $historic */
    use App\Domain\Pillars\Enums\RatingCommunity;
@endphp

@section('content')
    <main class="rating-list">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Navy Ratings</span>
        </nav>

        @include('partials.trust.disclosure')

        <header class="rating-hero">
            <p class="eyebrow">// U.S. Navy Reference</p>
            <h1>{{ $page->h1 ?? 'NAVY RATINGS' }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.byline')
        @include('partials.trust.key-facts')

        {{-- Active ratings, one section per community in the enum's canonical order. --}}
        @foreach (RatingCommunity::cases() as $community)
            @php($group = $activeByCommunity->get($community->value))
            @continue($group === null || $group->isEmpty())
            <section id="community-{{ $community->value }}" class="rating-section" aria-label="{{ $community->label() }} ratings">
                <h2>{{ $community->label() }}</h2>
                <ul class="rating-rows">
                    @foreach ($group as $rating)
                        <li id="{{ $rating->slug }}" class="rating-row">
                            <span class="abbr">{{ $rating->abbreviation }}</span>
                            @if ($rating->insignia_path)
                                <img class="insignia" src="{{ $rating->insignia_path }}" alt="{{ $rating->insignia_alt }}" width="40" height="40" loading="lazy">
                            @endif
                            <span class="rating-name">{{ $rating->name }}</span>
                            <span class="paygrade">{{ $rating->paygrade }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        {{-- Any active ratings whose community isn't one of the known enum cases
             (e.g. a null community) still render here, so the visible list stays
             consistent with the JSON-LD ItemList + the meta count (all three cover
             every active rating). --}}
        @php($known = collect(RatingCommunity::cases())->map->value->all())
        @foreach ($activeByCommunity as $key => $group)
            @continue(in_array($key, $known, true) || $group->isEmpty())
            <section class="rating-section" aria-label="Other ratings">
                <h2>Other</h2>
                <ul class="rating-rows">
                    @foreach ($group as $rating)
                        <li id="{{ $rating->slug }}" class="rating-row">
                            <span class="abbr">{{ $rating->abbreviation }}</span>
                            @if ($rating->insignia_path)
                                <img class="insignia" src="{{ $rating->insignia_path }}" alt="{{ $rating->insignia_alt }}" width="40" height="40" loading="lazy">
                            @endif
                            <span class="rating-name">{{ $rating->name }}</span>
                            <span class="paygrade">{{ $rating->paygrade }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @if ($historic->isNotEmpty())
            <section id="historic" class="rating-section historic" aria-label="Historic ratings">
                <h2>HISTORIC RATINGS (DISESTABLISHED)</h2>
                <ul class="rating-rows">
                    @foreach ($historic as $rating)
                        <li id="{{ $rating->slug }}" class="rating-row">
                            <span class="abbr">{{ $rating->abbreviation }}</span>
                            @if ($rating->insignia_path)
                                <img class="insignia" src="{{ $rating->insignia_path }}" alt="{{ $rating->insignia_alt }}" width="40" height="40" loading="lazy">
                            @endif
                            <span class="rating-name">{{ $rating->name }}</span>
                            <span class="paygrade">@if ($rating->decommissioned_year)Ret. {{ $rating->decommissioned_year }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <footer class="rating-footer">
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('ranks') }}">← Navy Ranks</a>
        </footer>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
