@extends('layouts.base')

{{-- /navy-reference/ — the reference library landing page every "← Navy Reference"
     back link points at. A 1:1 port of src/page-views/NavyReference.tsx: breadcrumb,
     disclosure, eyebrow + hero, byline, the 9 directory cards, the "See the fleet in
     person" band and the editorial-policy footer. Card counts are live from the
     pillars. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    /** @var list<array{badge: string, title: string, href: string, description: string}> $cards */
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Pillars\Models\NavyWeekEvent> $upcoming */
    // lucide ArrowRight — the legacy card CTAs use the icon, not a text glyph.
    $arrow = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>';
    $arrowLg = str_replace('width="12" height="12"', 'width="14" height="14"', $arrow);
    // formatShortDateRange (src/data/data.ts).
    $shortRange = static function ($e): string {
        $s = $e->start_date;
        $t = $e->end_date;

        return $s->format('M') === $t->format('M')
            ? $s->format('M d').' – '.$t->format('d, Y')
            : $s->format('M d').' – '.$t->format('M d, Y');
    };
@endphp

@section('content')
    <main class="reference-hub">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Navy Reference</span>
        </nav>

        @include('partials.trust.disclosure')

        <div class="reference-hub-eyebrow">// U.S. Navy Reference</div>
        <h1>{{ $page->h1 ?? $page->title }}</h1>
        <p class="reference-hub-intro">General U.S. Navy reference material — separate from Navy Week event coverage. Background on bases, ranks, officer designators, and veteran benefits, for readers researching the service itself rather than the touring outreach program.</p>

        @include('partials.trust.byline')

        <ul class="reference-hub-cards">
            @foreach ($cards as $card)
                <li>
                    <a href="{{ $card['href'] }}">
                        <span class="reference-hub-badge">{{ $card['badge'] }}</span>
                        <span class="reference-hub-title">{{ $card['title'] }}</span>
                        <span class="reference-hub-desc">{{ $card['description'] }}</span>
                        <span class="reference-hub-open">Open {!! $arrow !!}</span>
                    </a>
                </li>
            @endforeach
        </ul>

        <section class="reference-hub-fleet" aria-label="Navy Week events">
            <div class="reference-hub-eyebrow">// Navy Week 2026</div>
            <h2>SEE THE FLEET IN PERSON</h2>
            <p>This reference library sits alongside our coverage of the Navy Week touring outreach program — browse the full 2026 schedule or jump to an upcoming host city.</p>
            <ul class="reference-hub-fleet-cards">
                <li>
                    <a class="is-primary" href="/schedule/">
                        <span class="reference-hub-badge">Full Schedule</span>
                        <span class="reference-hub-fleet-title">2026 Navy Week Schedule {!! $arrowLg !!}</span>
                    </a>
                </li>
                @foreach ($upcoming as $event)
                    <li>
                        <a href="{{ PagePaths::child('navy_week_cities', $event->slug) }}">
                            <span class="reference-hub-badge">{{ $shortRange($event) }}</span>
                            <span class="reference-hub-fleet-title">{{ $event->city }} {!! $arrowLg !!}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
