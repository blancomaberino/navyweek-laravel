@extends('layouts.base')

{{-- Single air-show guide (/air-show/{slug}/). Body ported 1:1 from the legacy
     src/page-views/AirShowDetail.tsx (inline styles → resources/css/families/air-show.css).
     Head/JSON-LD (Article + WebPage + author/reviewer Person + FAQPage + Event) is
     byte-locked by SeoHead + AirShowPageSchema. --}}
@php
    /** @var \App\Domain\Pillars\Models\AirShow $show */
    /** @var string $hubPath */
    $hubPath ??= \App\Domain\Publishing\Support\PagePaths::root('air_shows');

    // Port of AirShowDetail's `renderInline`: **bold**, *italic* and [label](href).
    // Everything outside a match is escaped, and hrefs go through the same scheme
    // allowlist as editable nav links, so stored copy can never inject markup or an
    // executable href.
    $inline = static function (?string $text): string {
        $text = (string) $text;
        $out = '';
        $offset = 0;
        $pattern = '/\[([^\]]+)\]\(([^)]+)\)|\*\*(.+?)\*\*|\*(.+?)\*/';

        while (preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $m[0][1];
            $out .= e(substr($text, $offset, $start - $offset));

            if (isset($m[2]) && $m[2][1] !== -1) {
                $href = \App\Domain\Navigation\Support\LinkUrl::sanitize($m[2][0]);
                $external = ! str_starts_with($href, '/');
                $out .= '<a class="as-link" href="'.e($href).'"'
                    .($external ? ' target="_blank" rel="noopener noreferrer"' : '')
                    .'>'.e($m[1][0]).'</a>';
            } elseif (isset($m[3]) && $m[3][1] !== -1) {
                $out .= '<strong>'.e($m[3][0]).'</strong>';
            } elseif (isset($m[4]) && $m[4][1] !== -1) {
                $out .= '<em>'.e($m[4][0]).'</em>';
            }

            $offset = $start + strlen($m[0][0]);
        }

        return $out.e(substr($text, $offset));
    };

    // Port of the legacy `RelatedSegment` (AirShowDetail.tsx): a cross-link is a
    // real <a> only when its target actually ships; an unpublished sibling renders
    // as plain off-white text rather than a dead link.
    $publishedHrefs ??= [];
    $relatedSegment = static function (?string $before, string $label, ?string $href, ?string $after) use ($publishedHrefs): string {
        $target = $href !== null && in_array('/'.trim($href, '/').'/', $publishedHrefs, true)
            ? '<a class="as-link" href="'.e(\App\Domain\Navigation\Support\LinkUrl::sanitize($href)).'">'.e($label).'</a>'
            : '<span class="as-related-plain">'.e($label).'</span>';

        return e((string) $before).$target.e((string) $after);
    };

    $isFree = $show->admission->value === 'FREE';
    $organizer = is_array($show->organizer) ? ($show->organizer['name'] ?? null) : null;
    $emailCta = is_array($show->email_cta) ? $show->email_cta : null;
@endphp

@section('content')
    <main class="as-page">
        <section class="as-section as-hero">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <a href="/">Home</a>
                <span aria-hidden="true">/</span>
                <a href="{{ $hubPath }}">Air Shows</a>
                <span aria-hidden="true">/</span>
                <span aria-current="page">{{ $show->short_name }}</span>
            </nav>

            <section class="trust-disclosure" aria-label="Independence and editorial disclosure">
                <div class="trust-disclosure-label">Disclosure</div>
                <p>
                    The <strong>{{ $show->name }}</strong> is organized by {{ $organizer ?? $show->name }}. NavyWeek.org is an
                    independent guide and is <strong>not affiliated with, endorsed by, or sponsored by</strong> the show, its
                    organizers, the U.S. military, or any participating squadron. Dates, performers, and
                    ticketing are set by the organizer and the Department of Defense and can change &mdash; always
                    confirm current details with the official source before you travel.
                </p>
            </section>

            <div class="as-pills">
                <span class="as-pill {{ $isFree ? 'as-pill-free' : 'as-pill-ticketed' }}">{{ $isFree ? 'Free event' : 'Ticketed event' }}</span>
                <span class="as-place">{{ $show->city }}, {{ $show->state }}</span>
                @if ($show->date_unconfirmed)
                    <span class="as-pill as-pill-ticketed">{{ $show->year }} dates to be confirmed</span>
                @endif
                @if ($show->status->value !== 'scheduled')
                    <span class="as-pill as-pill-status">{{ $show->status->value === 'cancelled' ? 'Cancelled' : 'Postponed' }}</span>
                @endif
            </div>

            <h1>{{ $show->h1 }}</h1>

            @if ($show->hero_headline)
                <p class="as-dateline">{!! $inline($show->hero_headline) !!}</p>
            @endif

            @foreach ($show->intro ?? [] as $paragraph)
                <p class="as-p">{!! $inline($paragraph) !!}</p>
            @endforeach

            @include('partials.trust.byline')

            @include('partials.trust.key-facts', ['keyFacts' => filled($show->quick_facts) ? [
                'title' => $show->name.' '.$show->year.' — Key Facts',
                'ariaLabel' => $show->name.' key facts',
                'facts' => $show->quick_facts,
                'lastVerified' => $show->last_verified,
            ] : null])
        </section>

        @foreach ($show->sections ?? [] as $si => $section)
            <section class="as-section as-body" aria-labelledby="section-{{ $si }}">
                @isset($section['heading'])
                    <h2 id="section-{{ $si }}">{{ mb_strtoupper($section['heading']) }}</h2>
                @endisset

                {{-- Body copy is a list of typed blocks ({kind: p|ul|cta}); older
                     records instead carry flat `paragraphs` + `bullets` arrays. --}}
                @if (! empty($section['blocks']))
                    @foreach ($section['blocks'] as $block)
                        @if (($block['kind'] ?? null) === 'p')
                            <p class="as-p">{!! $inline($block['text'] ?? '') !!}</p>
                        @elseif (($block['kind'] ?? null) === 'ul')
                            <ul>
                                @foreach ($block['items'] ?? [] as $item)
                                    <li>{!! $inline($item) !!}</li>
                                @endforeach
                            </ul>
                        @elseif (($block['kind'] ?? null) === 'cta' && $emailCta)
                            {{-- Styled email-capture stub. It deliberately does nothing on
                                 submit — the legacy component is a placeholder to be wired
                                 to an email service provider later. --}}
                            <form class="as-email-cta" onsubmit="return false">
                                <div class="as-email-heading">{{ $emailCta['heading'] ?? '' }}</div>
                                <div class="as-email-row">
                                    <input type="email" required aria-label="Email address" placeholder="{{ $emailCta['placeholder'] ?? 'Enter your email' }}">
                                    <button type="submit">{{ $emailCta['buttonLabel'] ?? '' }}</button>
                                </div>
                            </form>
                        @endif
                    @endforeach
                @else
                    @foreach ($section['paragraphs'] ?? [] as $paragraph)
                        <p class="as-p">{!! $inline($paragraph) !!}</p>
                    @endforeach
                    @if (! empty($section['bullets']))
                        <ul>
                            @foreach ($section['bullets'] as $bullet)
                                <li>{!! $inline($bullet) !!}</li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </section>
        @endforeach

        @if ($show->sources->isNotEmpty())
            <section class="as-section as-sources" aria-labelledby="sources">
                <h2 id="sources">SOURCES</h2>
                <ul>
                    @foreach ($show->sources as $source)
                        <li>
                            @if ($source->url)
                                <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize($source->url) }}" rel="noopener noreferrer nofollow" target="_blank">{{ $source->label }}</a>
                            @else
                                {{ $source->label }}
                            @endif
                            @if ($source->publisher)
                                <span class="as-source-pub"> — {{ $source->publisher }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($show->faqs->isNotEmpty())
        <section class="as-section as-faq" aria-labelledby="faq">
            <h2 id="faq">FREQUENTLY ASKED QUESTIONS</h2>
            {{-- CSS-only accordion (native <details>), first item open — port of
                 FAQAccordion in the legacy DiscountDetail.tsx. --}}
            <div class="nw-faq-list">
                @foreach ($show->faqs as $faq)
                    <details class="nw-faq" @if ($loop->first) open @endif>
                        <summary>
                            <h3>{{ $faq->question }}</h3>
                            <svg class="nw-faq-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                        </summary>
                        <div class="nw-faq-a">{{ $faq->answer }}</div>
                    </details>
                @endforeach
            </div>
        </section>
        @endif

        <section class="as-section as-related" aria-labelledby="related">
            <h2 id="related">NEARBY &amp; RELATED</h2>

            {{-- `related_paragraph` is a single string, a list of strings, or a list of
                 {before,label,href,after} link fragments — all three render as one <p>,
                 with the fragments concatenated and NO separator between them (the
                 legacy maps them straight into one paragraph). --}}
            @php
                $relatedHtml = collect((array) $show->related_paragraph)
                    ->map(fn ($segment): string => is_array($segment)
                        ? $relatedSegment($segment['before'] ?? '', (string) ($segment['label'] ?? $segment['href'] ?? ''), $segment['href'] ?? null, $segment['after'] ?? '')
                        : e((string) $segment))
                    ->implode('');
            @endphp
            @if ($relatedHtml !== '')
                <p class="as-p">{!! $relatedHtml !!}</p>
            @endif

            <a class="as-back" href="{{ $hubPath }}">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                All military air shows
            </a>

            @include('partials.trust.editorial-policy')
        </section>
    </main>
@endsection
