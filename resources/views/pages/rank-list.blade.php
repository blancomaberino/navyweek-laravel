@extends('layouts.base')

{{-- /navy-ranks/ — every officer + enlisted rank on one page, three paygrade-ordered
     sections rendered high→low (each row anchored by slug for /navy-ranks/#<slug>).
     Head/JSON-LD is byte-locked by SeoHead + RankListSchema; this body is a clean
     semantic rebuild. --}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank> $commissioned */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank> $warrant */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\Rank> $enlisted */
    $sections = [
        'Commissioned Officers' => $commissioned,
        'Warrant Officers' => $warrant,
        'Enlisted Paygrades' => $enlisted,
    ];
@endphp

@section('content')
    <main class="rank-list">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Navy Ranks</span>
        </nav>

        <header class="rank-hero">
            <p class="eyebrow">// U.S. Navy Reference</p>
            <h1>Navy Ranks</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @foreach ($sections as $heading => $ranks)
            @continue($ranks->isEmpty())
            <section class="rank-section" aria-label="{{ $heading }}">
                <h2>{{ $heading }}</h2>
                <ul class="rank-rows">
                    {{-- High → low: the repository returns ascending paygrade order. --}}
                    @foreach ($ranks->reverse() as $rank)
                        <li id="{{ $rank->slug }}" class="rank-row">
                            <span class="paygrade">{{ $rank->paygrade }}</span>
                            @if ($rank->insignia_path)
                                <img class="insignia" src="{{ $rank->insignia_path }}" alt="{{ $rank->insignia_alt }}" width="40" height="40" loading="lazy">
                            @endif
                            <span class="rank-name">{{ $rank->name }} <span class="abbr">({{ $rank->abbreviation }})</span></span>
                            @if ($rank->nato_code)
                                <span class="nato">{{ $rank->nato_code }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <footer class="rank-footer">
            <a href="/navy-ratings/">Navy Ratings →</a>
        </footer>
    </main>
@endsection
