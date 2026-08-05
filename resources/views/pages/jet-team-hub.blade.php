@extends('layouts.base')

{{-- Jet-team hub (/{team}/). Season schedule directory + JSON-LD ItemList. Head/JSON-LD
     byte-locked by SeoHead + JetTeamPageSchema::buildHub. Markup + spacing ported 1:1
     from the legacy src/page-views/JetTeamHub.tsx (its styles are inline; the values
     live in resources/css/families/jet-team.css). --}}
@php
    /** @var \App\Domain\Pillars\Models\JetTeam $team */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\JetTeamScheduleRow> $schedule */
    /** @var array<int, string> $guideSlugs */
    $intro = is_array($team->intro) ? $team->intro : [];
    $about = is_array($team->about) ? $team->about : [];
    $crossTeam = is_array($team->cross_team) ? $team->cross_team : [];
    $year = $team->year ?? now()->year;
@endphp

@section('content')
    <main class="jet-team-hub">
        <section class="jt-section jt-wide jt-hero">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $team->name }}</span>
            </nav>

            {{-- Independence disclosure. Written out here rather than via
                 partials.trust.disclosure because the legacy copy carries inline
                 <strong> emphasis that an escaped string slot cannot express; the
                 classes (and therefore the styling) are the shared ones. --}}
            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>NavyWeek.org is an independent guide. The {{ $team->name }} are operated by the
                    {{ $team->branch }}, and each air show is run by its own host or organizer. We are
                    <strong>not affiliated with, endorsed by, or sponsored by</strong> the {{ $team->branch }},
                    the squadron, or any show organizer. Dates and schedules are set by the military and the
                    organizers and can change at any time. Always confirm current details with the official
                    source before you travel.</p>
            </section>

            <div class="jt-eyebrow">{{ $team->eyebrow }}</div>
            <h1 class="jt-h1-hub">{{ $team->hub_title }}</h1>
            @if ($team->hub_subtitle)
                <div class="jt-hub-sub">{{ $team->hub_subtitle }}</div>
            @endif
            @foreach ($intro as $paragraph)
                <p class="jt-p jt-p-820">{{ $paragraph }}</p>
            @endforeach

            @include('partials.trust.byline')

            @include('partials.trust.key-facts', ['keyFacts' => filled($team->key_facts) ? [
                'title' => $team->name.' '.$year.' — Key Facts',
                'facts' => $team->key_facts,
                'lastVerified' => $team->last_verified,
                'ariaLabel' => $team->name.' key facts',
            ] : null])
        </section>

        <section class="jt-section jt-wide" aria-labelledby="schedule">
            <h2 class="jt-h2" id="schedule">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--gold)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-calendar-days" aria-hidden="true"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path><path d="M8 14h.01"></path><path d="M12 14h.01"></path><path d="M16 14h.01"></path><path d="M8 18h.01"></path><path d="M12 18h.01"></path><path d="M16 18h.01"></path></svg>{{ $year }} TOUR SCHEDULE
            </h2>
            @if ($schedule->isEmpty())
                <p class="jt-p">The schedule is being finalized.</p>
            @else
                <div class="jt-table-wrap">
                    <table class="jt-schedule">
                        <caption>{{ $team->full_name }} {{ $year }} — every stop on the official tour</caption>
                        <thead>
                            <tr>
                                <th scope="col">Dates</th>
                                <th scope="col">City</th>
                                <th scope="col">Show</th>
                                <th scope="col">Guide</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($schedule as $row)
                                @php
                                    $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) ($row->status ?? 'scheduled');
                                    $admission = $row->admission instanceof \BackedEnum ? $row->admission->value : $row->admission;
                                @endphp
                                <tr>
                                    <th scope="row">{{ $row->dates_label }}</th>
                                    <td class="jt-cell-city">
                                        {{ $row->city }}, {{ $row->state }}
                                        @if ($row->venue)
                                            <span class="jt-venue">{{ $row->venue }}</span>
                                        @endif
                                    </td>
                                    <td class="jt-cell-show">
                                        <span class="jt-show-inner">
                                            {{ $row->show }}
                                            @if ($admission)
                                                <span class="jt-pill {{ $admission === 'FREE' ? 'jt-pill-free' : 'jt-pill-ticketed' }}">{{ $admission === 'FREE' ? 'Free' : 'Ticketed' }}</span>
                                            @endif
                                            @if ($status !== 'scheduled')
                                                <span class="jt-pill {{ $status === 'completed' ? 'jt-pill-completed' : 'jt-pill-alert' }}">{{ $status }}</span>
                                            @endif
                                        </span>
                                    </td>
                                    <td>
                                        @if (in_array($row->slug, $guideSlugs, true))
                                            <a class="jt-guide-link" href="{{ $team->base_path }}/{{ $row->slug }}/">{{ $row->guide_label ?? $row->city }} guide</a>
                                        @else
                                            <span class="jt-guide-soon">Guide coming soon</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @if ($about !== [])
            <section class="jt-section jt-narrow-flush" aria-labelledby="about">
                <h2 class="jt-h2" id="about">ABOUT THE {{ $team->hub_title }}</h2>
                @foreach ($about as $paragraph)
                    <p class="jt-p jt-p-760">{{ $paragraph }}</p>
                @endforeach
            </section>
        @endif

        <section class="jt-section jt-narrow-flush" aria-labelledby="faq">
            <h2 class="jt-h2" id="faq">FREQUENTLY ASKED QUESTIONS</h2>
            @if ($team->faqs->isNotEmpty())
                <div class="jt-faq-list">
                    @foreach ($team->faqs as $faq)
                        <details class="jt-faq" @if ($loop->first) open @endif>
                            <summary>
                                <h3>{{ $faq->question }}</h3>
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-chevron-down jt-faq-chev" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg>
                            </summary>
                            <div class="jt-faq-a">{{ $faq->answer }}</div>
                        </details>
                    @endforeach
                </div>
            @endif

            @if ($crossTeam !== [])
                <p class="jt-p jt-p-760 jt-cross-team">{{ $crossTeam['before'] ?? '' }}@if (! empty($crossTeam['href']))<a href="{{ $crossTeam['href'] }}">{{ $crossTeam['label'] ?? '' }}</a>@else{{ $crossTeam['label'] ?? '' }}@endif{{ $crossTeam['after'] ?? '' }}</p>
            @endif

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
