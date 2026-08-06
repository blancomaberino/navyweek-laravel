@extends('layouts.base')

{{-- Jet-team city guide (/{team}/{slug}/). Head/JSON-LD (Article + WebPage + Person×2 +
     FAQPage + Event) byte-locked by SeoHead + JetTeamPageSchema::buildCity. Markup +
     spacing ported 1:1 from the legacy src/page-views/JetTeamDetail.tsx (its styles are
     inline; the values live in resources/css/families/jet-team.css). --}}
@php
    /** @var \App\Domain\Pillars\Models\JetTeamCity $city */
    /** @var \App\Domain\Pillars\Models\JetTeam $team */
    /** @var \App\Domain\Pillars\Models\JetTeamScheduleRow|null $prevStop */
    /** @var \App\Domain\Pillars\Models\JetTeamScheduleRow|null $nextStop */
    /** @var array{team: \App\Domain\Pillars\Models\JetTeam, row: \App\Domain\Pillars\Models\JetTeamScheduleRow}|null $sibling */
    /** @var array<int, string> $publishedHrefs */
    $prevStop ??= null;
    $nextStop ??= null;
    $sibling ??= null;
    $publishedHrefs ??= [];
    $intro = is_array($city->intro) ? $city->intro : [];
    $sections = is_array($city->sections) ? $city->sections : [];
    $admission = $city->admission instanceof \BackedEnum ? $city->admission->value : (string) $city->admission;
    $status = $city->status instanceof \BackedEnum ? $city->status->value : (string) ($city->status ?? 'scheduled');

    // Author copy uses **bold** / *italic* sparingly (a "**lead:**" on a bullet, an
    // *emphasis* mid-paragraph) — the legacy `renderInline`. Escape first, then
    // promote the markers, so page copy can never inject markup.
    $inline = static function (string $text): string {
        $html = e($text);
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html) ?? $html;

        return preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html) ?? $html;
    };

    // Port of the legacy `RelatedSegment` (JetTeamDetail.tsx): a cross-link is a
    // real <a> only when its target actually ships, otherwise it renders as plain
    // off-white text. Segments concatenate with NO separator — they are authored
    // as one sentence split around its links.
    $teamRoot = rtrim($team->base_path, '/').'/';
    $isPublished = static fn (?string $href): bool => $href !== null
        && in_array('/'.trim($href, '/').'/', $publishedHrefs, true);
    $segment = static function (?string $before, string $label, ?string $href, ?string $after) use ($isPublished): string {
        $target = $href !== null && $isPublished($href)
            ? '<a href="'.e(\App\Domain\Navigation\Support\LinkUrl::sanitize($href)).'">'.e($label).'</a>'
            : '<span class="jt-related-plain">'.e($label).'</span>';

        return e((string) $before).$target.e((string) $after);
    };
@endphp

@section('content')
    <main class="jet-team-city">
        <section class="jt-section jt-narrow jt-hero">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($team->base_path.'/') }}">{{ $team->name }}</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $city->city }}</span>
            </nav>

            {{-- Independence disclosure. Written out here rather than via
                 partials.trust.disclosure because the legacy copy carries inline
                 <strong> emphasis that an escaped string slot cannot express; the
                 classes (and therefore the styling) are the shared ones. --}}
            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>The <strong>{{ $city->show }}</strong> is run by a third-party host, and the
                    {{ $team->name }} are operated by the {{ $team->branch }}. NavyWeek.org is an
                    independent guide and is <strong>not affiliated with, endorsed by, or sponsored by</strong>
                    the show, its organizers, the squadron, or the {{ $team->branch }}. Dates, show times, and
                    ticketing are set by the organizer and can change — always confirm current details with the
                    official source before you travel.</p>
            </section>

            <div class="jt-badges">
                <span class="jt-badge {{ $admission === 'FREE' ? 'jt-badge-free' : 'jt-badge-ticketed' }}">{{ $admission === 'FREE' ? 'Free event' : 'Ticketed event' }}</span>
                <span class="jt-locline">{{ $city->city }}, {{ $city->state }}</span>
                @if ($status !== 'scheduled')
                    <span class="jt-badge jt-badge-alert">{{ $status }}</span>
                @endif
            </div>

            <h1 class="jt-h1-city">{{ $city->h1 }}</h1>
            @if ($city->hero_dateline)
                <p class="jt-dateline @if ($city->second_dates_label) jt-dateline-has-second @endif">{{ $city->hero_dateline }}</p>
            @endif
            @if ($city->second_dates_label)
                <p class="jt-second-dates">Also returns this season: {{ $city->second_dates_label }}</p>
            @endif

            @foreach ($intro as $paragraph)
                <p class="jt-p">{!! $inline($paragraph) !!}</p>
            @endforeach

            @include('partials.trust.byline')

            @include('partials.trust.key-facts', ['keyFacts' => filled($city->quick_facts) ? [
                'title' => trim(($team->name ?? '').' '.$city->city.' '.$city->year).' — Key Facts',
                'facts' => $city->quick_facts,
                'lastVerified' => $city->last_verified,
                'ariaLabel' => trim(($team->name ?? '').' '.$city->city).' key facts',
            ] : null])
        </section>

        @foreach ($sections as $section)
            <section class="jt-section jt-narrow">
                @isset($section['heading'])
                    <h2 class="jt-h2">{{ mb_strtoupper($section['heading']) }}</h2>
                @endisset
                @foreach ($section['paragraphs'] ?? [] as $paragraph)
                    <p class="jt-p">{!! $inline($paragraph) !!}</p>
                @endforeach
                @if (! empty($section['bullets']))
                    <ul class="jt-bullets">
                        @foreach ($section['bullets'] as $bullet)
                            <li>{!! $inline($bullet) !!}</li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endforeach

        @if ($city->sources->isNotEmpty())
            <section class="jt-section jt-narrow" aria-labelledby="sources">
                <h2 class="jt-h2" id="sources">SOURCES</h2>
                <ul class="jt-sources">
                    @foreach ($city->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($source->url) }}" rel="noopener noreferrer nofollow" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                            @if ($source->publisher)
                                <span class="jt-source-publisher"> — {{ $source->publisher }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="jt-section jt-narrow" aria-labelledby="faq">
            <h2 class="jt-h2" id="faq">FREQUENTLY ASKED QUESTIONS</h2>
            @if ($city->faqs->isNotEmpty())
                <div class="jt-faq-list">
                    @foreach ($city->faqs as $faq)
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
        </section>

        <section class="jt-section jt-narrow jt-section-last" aria-labelledby="related">
            <h2 class="jt-h2" id="related">NEARBY &amp; RELATED</h2>
            {{-- `related_paragraph` is a list of {before,label,href,after} link
                 fragments (or plain strings) that form ONE paragraph — the legacy
                 maps them inside a single <p>, so they must not be split. --}}
            @php
                $relatedHtml = collect((array) $city->related_paragraph)
                    ->map(fn ($seg): string => is_array($seg)
                        ? $segment($seg['before'] ?? '', (string) ($seg['label'] ?? $seg['href'] ?? ''), $seg['href'] ?? null, $seg['after'] ?? '')
                        : e((string) $seg))
                    ->implode('');
            @endphp
            @if ($relatedHtml !== '')
                <p class="jt-p">{!! $relatedHtml !!}</p>
            @endif

            @if ($sibling !== null)
                @php
                    $siblingHref = rtrim($sibling['team']->base_path, '/').'/'.$sibling['row']->slug.'/';
                @endphp
                <p class="jt-p">The {{ $sibling['team']->name }} are at the same show this year —
                    @if ($isPublished($siblingHref))<a href="{{ $siblingHref }}">see the {{ $sibling['team']->name }} {{ $sibling['row']->city }} guide</a>@else<span>both teams headline {{ $sibling['row']->city }} on {{ $sibling['row']->dates_label }}</span>@endif.</p>
            @endif

            @if ($prevStop !== null || $nextStop !== null)
                <p class="jt-other-stops"><span class="jt-other-stops-label">Other {{ $city->year }} stops:</span>@if ($prevStop !== null)<span>{!! $segment(null, '← '.($prevStop->guide_label ?? $prevStop->city), $teamRoot.$prevStop->slug.'/', null) !!}</span>@endif@if ($prevStop !== null && $nextStop !== null)<span class="jt-other-stops-sep"> · </span>@endif@if ($nextStop !== null)<span>{!! $segment(null, ($nextStop->guide_label ?? $nextStop->city).' →', $teamRoot.$nextStop->slug.'/', null) !!}</span>@endif</p>
            @endif

            <a class="jt-back-link" href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($team->base_path.'/') }}">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-right" aria-hidden="true"><path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path></svg>
                Full {{ $team->name }} {{ $city->year }} schedule
            </a>

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
