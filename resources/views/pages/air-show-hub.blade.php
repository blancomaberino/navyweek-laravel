@extends('layouts.base')

{{-- Air-show hub (/air-show/). Body ported 1:1 from the legacy
     src/page-views/AirShowHub.tsx (inline styles → resources/css/families/air-show.css):
     disclosure + hero, the show directory table, the about copy and the FAQs.
     Head/JSON-LD (Article + ItemList + FAQPage) is byte-locked by SeoHead +
     AirShowPageSchema::buildHub. --}}
@php
    /** @var \App\Domain\Pillars\Models\AirShowHubMeta $hub */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\AirShow> $shows */
@endphp

@section('content')
    <main class="ash-page">
        <section class="ash-section ash-hero">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">Air Shows</span>
            </nav>

            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>
                    NavyWeek.org is an independent guide. Military air shows are run by their own hosts and
                    organizers, and the flight demonstration teams are operated by the U.S. military. We are
                    <strong>not affiliated with, endorsed by, or sponsored by</strong> any show organizer,
                    squadron, or branch of the armed forces. Dates, performers, and schedules are set by the
                    military and the organizers and can change at any time. Always confirm current details
                    with the official source before you travel.
                </p>
            </section>

            @if ($hub->eyebrow)
                <div class="ash-eyebrow">{{ $hub->eyebrow }}</div>
            @endif

            <h1>{{ $hub->hub_title }}</h1>

            @if ($hub->hub_subtitle)
                <div class="ash-subtitle">{{ $hub->hub_subtitle }}</div>
            @endif

            @foreach ($hub->intro ?? [] as $paragraph)
                <p class="ash-p">{{ $paragraph }}</p>
            @endforeach

            @include('partials.trust.byline')

            @include('partials.trust.key-facts', ['keyFacts' => filled($hub->key_facts) ? [
                'title' => 'U.S. Military Air Shows '.$hub->year.' — Key Facts',
                'ariaLabel' => 'Military air shows key facts',
                'facts' => $hub->key_facts,
                'lastVerified' => $hub->last_verified,
            ] : null])
        </section>

        <section class="ash-section ash-shows" aria-labelledby="shows">
            <h2 id="shows">
                <svg class="ash-h2-icon" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/><path d="M8 14h.01"/><path d="M12 14h.01"/><path d="M16 14h.01"/><path d="M8 18h.01"/><path d="M12 18h.01"/><path d="M16 18h.01"/></svg>
                {{ $hub->year }} MILITARY AIR SHOWS
            </h2>

            <div class="ash-table-wrap">
                <table>
                    <caption>Major U.S. military air shows — {{ $hub->year }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">Dates</th>
                            <th scope="col">Show</th>
                            <th scope="col">Location</th>
                            <th scope="col">Guide</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($shows as $show)
                            <tr>
                                <th scope="row">{{ $show->dates_label }}</th>
                                <td class="ash-show">
                                    <span class="ash-show-name">
                                        {{ $show->name }}
                                        <span class="ash-micro {{ $show->admission->value === 'FREE' ? 'ash-micro-free' : 'ash-micro-ticketed' }}">{{ $show->admission->value === 'FREE' ? 'Free' : 'Ticketed' }}</span>
                                        @if ($show->status->value !== 'scheduled')
                                            <span class="ash-micro ash-micro-status">{{ $show->status->value }}</span>
                                        @endif
                                    </span>
                                    <span class="ash-headliner">Headliner: {{ $show->headliner }}</span>
                                </td>
                                <td class="ash-loc">{{ $show->city }}, {{ $show->state }}</td>
                                <td class="ash-guide">
                                    @if ($show->published)
                                        <a href="/air-show/{{ $show->slug }}/">{{ $show->short_name }} guide</a>
                                    @else
                                        <span class="ash-soon">Guide coming soon</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="ash-section ash-about" aria-labelledby="about">
            <h2 id="about">ABOUT MILITARY AIR SHOWS</h2>
            @foreach ($hub->about ?? [] as $paragraph)
                <p class="ash-p ash-p-narrow">{{ $paragraph }}</p>
            @endforeach
        </section>

        <section class="ash-section ash-faq" aria-labelledby="faq">
            <h2 id="faq">FREQUENTLY ASKED QUESTIONS</h2>
            {{-- CSS-only accordion (native <details>), first item open — port of
                 FAQAccordion in the legacy DiscountDetail.tsx. --}}
            <div class="nw-faq-list">
                @foreach ($hub->faqs as $faq)
                    <details class="nw-faq" @if ($loop->first) open @endif>
                        <summary>
                            <h3>{{ $faq->question }}</h3>
                            <svg class="nw-faq-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="nw-faq-a">{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </div>

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
