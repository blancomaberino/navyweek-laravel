@extends('layouts.base')

{{-- Jet-team hub (/{team}/). Season schedule directory + JSON-LD ItemList. Head/JSON-LD
     byte-locked by SeoHead + JetTeamPageSchema::buildHub. --}}
@php
    /** @var \App\Domain\Pillars\Models\JetTeam $team */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\JetTeamScheduleRow> $schedule */
    /** @var array<int, string> $guideSlugs */
    $intro = is_array($team->intro) ? $team->intro : [];
    $about = is_array($team->about) ? $team->about : [];
@endphp

@section('content')
    <main class="jet-team-hub">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $team->name }}</span>
        </nav>

        <header class="hub-hero">
            <p class="eyebrow">// {{ $team->branch }}</p>
            <h1>{{ $team->hub_title }}</h1>
            @if ($team->hub_subtitle)
                <p class="subtitle">{{ $team->hub_subtitle }}</p>
            @endif
            @foreach ($intro as $paragraph)
                <p class="intro">{{ $paragraph }}</p>
            @endforeach
        </header>

        @if (! empty($team->key_facts))
            <section class="hub-key-facts" aria-label="Key facts">
                <dl>
                    @foreach ($team->key_facts as $fact)
                        <div><dt>{{ $fact['label'] ?? '' }}</dt><dd>{{ $fact['value'] ?? '' }}</dd></div>
                    @endforeach
                </dl>
            </section>
        @endif

        <section class="hub-schedule" aria-label="{{ $team->name }} schedule">
            <h2>{{ $team->seo_headline }}</h2>
            @if ($schedule->isEmpty())
                <p class="empty-state">The schedule is being finalized.</p>
            @else
                <ul class="schedule-rows">
                    @foreach ($schedule as $row)
                        <li class="schedule-row">
                            <span class="dates">{{ $row->dates_label }}</span>
                            <span class="show">{{ $row->show }}</span>
                            <span class="loc">{{ $row->city }}, {{ $row->state }}</span>
                            @if (in_array($row->slug, $guideSlugs, true))
                                <a class="guide-link" href="{{ $team->base_path }}/{{ $row->slug }}/">City guide →</a>
                            @else
                                <span class="guide-soon">Guide coming soon</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @foreach ($about as $paragraph)
            <section class="hub-about"><p>{{ $paragraph }}</p></section>
        @endforeach

        @if ($team->faqs->isNotEmpty())
            <section class="hub-faqs" aria-label="Frequently asked questions">
                <h2>Frequently Asked Questions</h2>
                <dl>
                    @foreach ($team->faqs as $faq)
                        <dt>{{ $faq->question }}</dt>
                        <dd>{{ $faq->answer }}</dd>
                    @endforeach
                </dl>
            </section>
        @endif
    </main>
@endsection
