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

        {{-- Kicker + intro are the legacy hub's own hero copy (NavyRatingsHub.tsx),
             not the meta description — this family has exactly one page, so the
             copy belongs to the template the way rank-list.blade.php holds its own. --}}
        <header class="rating-hero">
            <p class="eyebrow">{{ $page->eyebrow ?? '// Enlisted Job Specialties' }}</p>
            <h1>{{ $page->h1 ?? 'NAVY RATINGS' }}</h1>
            <p class="intro">A rating is an enlisted Sailor's occupational specialty — the job title worn on the rating badge, from Hospital Corpsman (HM) to Boatswain's Mate (BM). Every active rating is listed below, grouped by community, followed by the historic ratings the Navy has disestablished or merged away.</p>
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
                <h2>OTHER</h2>
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
                <p class="section-intro">Ratings the Navy has disestablished, merged, or renamed — listed by the year they were retired.</p>
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

        {{-- "See Also" cross-link card, ported 1:1 from NavyRatingsHub.tsx. --}}
        <div class="rating-see-also">
            <div class="rating-see-also-eyebrow">See Also</div>
            <div class="rating-see-also-title">Navy Ranks</div>
            <div class="rating-see-also-body">Ratings are jobs; ranks are paygrades. See every commissioned officer, warrant officer, and enlisted paygrade on the Navy Ranks list.</div>
            <a class="rating-see-also-cta" href="{{ \App\Domain\Publishing\Support\PagePaths::root('ranks') }}">
                Browse Navy Ranks
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
            </a>
        </div>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
