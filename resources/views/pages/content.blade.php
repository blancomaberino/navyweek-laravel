@extends('layouts.base')

{{-- Generic DB-driven content page. Renders the CMS-editable `pages.body_blocks`
     (an ordered list of typed blocks) plus the trust chrome the legacy reference
     pages carry: the "← Navy Reference" back link, the page-specific independence
     disclosure, the hero eyebrow, the KeyFacts card and the FAQ answers. The
     head/JSON-LD is byte-locked by SeoHead + ContentPageSchema. Shared by the
     editorial content pages (privacy/terms/contact, our-process, the credit-cards
     guide, and the veterans-day / va-disability / veterans-home-care guides).

     Block types: heading · paragraph · list · list_item (legacy) · note · callout
     · table · toc · jump_nav · faq_item · card · link_card · info_card. A block
     carries either plain `text` or a list of inline `spans`
     ({text, bold?, italic?, url?}); `slot: 'hero'` moves it into the hero stack
     above the byline, and `variant` picks a page-specific treatment.

     Presentation is ported from the legacy inline styles in
     src/page-views/{VaDisability,VeteransDay,VeteransHomeCare,BestCreditCardsForMilitary,OurProcess,PrivacyPolicy,Terms,Contact}.tsx
     — see resources/css/families/ymyl.css. Legacy bodies arrive flat, so the
     groupings that markup has are rebuilt here: consecutive bullets fold into one
     list, the jump list ("On this page") is the first bullet run that still
     precedes every section heading, and a body heading that IS an FAQ question is
     paired back up with its answer from the page's `faqs` relation. --}}
@php
    use App\Domain\Navigation\Support\LinkUrl;
    use App\Domain\Publishing\Content\InlineSpans;

    /** @var list<array{name: string, url: string}> $crumbs */
    /** @var list<array<string, mixed>> $blocks */
    /** @var string $heading */
    /** @var iterable<int, \App\Domain\Shared\Models\Faq> $faqs */

    // Inline runs → HTML, and a block's plain words. Both live in InlineSpans so the
    // page and the CMS editor share ONE vocabulary — a mark added to one is readable
    // by the other by construction. Every editor-supplied href is sanitized in there.
    $inline = InlineSpans::render(...);

    $plain = InlineSpans::plainText(...);

    // Fold runs of `list_item` back into single lists (the legacy renders one <ul>
    // per bullet group, not one per bullet).
    $items = [];
    foreach ($blocks as $block) {
        $type = $block['type'] ?? 'paragraph';
        $last = array_key_last($items);

        if ($type === 'list_item' && $last !== null && $items[$last]['type'] === 'list_item') {
            $items[$last]['items'][] = (string) ($block['text'] ?? '');

            continue;
        }

        $items[] = $type === 'list_item'
            ? ['type' => 'list_item', 'items' => [(string) ($block['text'] ?? '')]]
            : ['type' => $type, 'block' => $block];
    }

    // The KeyFacts card is `pages.key_facts` (title + fact rows + source + last
    // verified), rendered by the shared partial. A leading heading in the body is
    // the same card's title carried over from the body import — drop it, along
    // with the "Source: … · Last verified: …" line under it, so the card is not
    // announced twice. A leading paragraph is instead the hero lead.
    $keyFacts = $page->key_facts;
    $keyFactsTitle = null;
    $keyFactsSource = null;
    $heroLead = null;

    // Blocks the body marks `slot: hero` render inside the hero stack, between the
    // h1 and the byline — where the legacy pages put their intro capsule, their
    // "fees verified" stamp and the policy pages' "Last updated" line. Drained both
    // before and after the lead/disclosure lift below, because the credit-cards
    // guide's hero run starts one block in (behind its advertiser disclosure).
    $heroBlocks = [];
    $drainHero = static function () use (&$items, &$heroBlocks): void {
        while (($items[0]['block']['slot'] ?? null) === 'hero') {
            $heroBlocks[] = array_shift($items)['block'];
        }
    };
    $drainHero();

    // A leading heading is the KeyFacts card's title carried over from the body
    // import — but only on the pages that HAVE that card, or whose next block is the
    // card's "Source: … · Last verified: …" line. Elsewhere it is a real section head.
    $leadingIsKeyFacts = ($items[0]['type'] ?? null) === 'heading'
        && (($keyFacts['facts'] ?? []) !== []
            || (($items[1]['type'] ?? null) === 'paragraph' && str_starts_with($plain($items[1]['block'] ?? []), 'Source:')));

    if ($leadingIsKeyFacts) {
        $keyFactsTitle = $plain($items[0]['block']);
        array_shift($items);

        $next = $items[0]['block'] ?? [];
        if (($items[0]['type'] ?? null) === 'paragraph' && str_starts_with($plain($next), 'Source:')) {
            $keyFactsSource = $plain($next);
            array_shift($items);
        }
    } elseif (($items[0]['type'] ?? null) === 'paragraph' && ! isset($items[0]['block']['variant'])) {
        // Only an untyped opening paragraph is the legacy hero lead; one that
        // names a variant is a body block and stays where the editor put it.
        $heroLead = $items[0]['block'];
        array_shift($items);
    }

    // The disclosure body is `pages.disclosure` (the wording is page-specific: the
    // VA guides name the VA, the credit-cards guide carries the FTC advertiser
    // language). On pages whose disclosure also landed in `body_blocks` as the
    // opening paragraph ("Advertiser & editorial disclosure: …"), drop that block
    // so the box can't render twice.
    $disclosure = $page->disclosure;
    if ($heroLead !== null && preg_match('/^.{0,60}?disclosure:/i', $plain($heroLead)) === 1) {
        $disclosure ??= $plain($heroLead);
        $heroLead = null;
    }

    $drainHero();


    // Paragraph variants: the legacy promotes a handful of intro paragraphs to a
    // larger face, and boxes two of them.
    $paragraphClass = static fn (array $block): string => match ($block['variant'] ?? null) {
        'capsule' => 'content-capsule',
        'fine' => 'content-fine',
        'op-lead' => 'op-lead',
        'op-reviewer' => 'op-reviewer',
        'lead' => 'content-lead',
        'panel' => 'content-panel',
        'stamp' => 'content-stamp',
        'sublead' => 'content-sublead',
        'verified' => 'content-verified',
        default => '',
    };

    // The legacy renders an author/reviewer byline on the guide pages only — the
    // policy pages (privacy/terms/contact) that share this view have none. The list
    // belongs in a CMS flag on the page; it lives here until that column exists.
    $showsByline = in_array($page->slug, [
        'best-credit-cards-for-military',
        'our-process',
        'va-disability',
        'veterans-day',
        'veterans-home-care',
    ], true);

    // The jump list is the first bullet run that still precedes every section heading.
    $tocIndex = null;
    $headingsSeen = 0;
    foreach ($items as $i => $item) {
        if ($item['type'] === 'heading') {
            $headingsSeen++;
        }
        if ($item['type'] === 'list_item' && $headingsSeen === 0) {
            $tocIndex = $i;

            break;
        }
    }

    // FAQ answers come from the page's `faqs` relation; the body only ever carries
    // the questions (as headings) or nothing at all. Key on the lower-cased question
    // so a body heading can be matched back to its answer.
    $faqAnswers = [];
    $faqRows = [];
    foreach ($faqs ?? [] as $faq) {
        $faqRows[] = ['question' => (string) $faq->question, 'answer' => (string) $faq->answer];
        $faqAnswers[mb_strtolower(trim((string) $faq->question))] = (string) $faq->answer;
    }

    // Two legacy treatments: the guides whose body repeats the questions as headings
    // render them expanded (heading + answer); the ones whose body has only the
    // "Frequently asked questions" section heading render a collapsed <details> list.
    $faqInline = false;
    foreach ($items as $item) {
        if ($item['type'] === 'faq_item') {
            $faqInline = true;

            break;
        }
        if ($item['type'] === 'heading' && isset($faqAnswers[mb_strtolower(trim($plain($item['block'])))])) {
            $faqInline = true;

            break;
        }
    }
@endphp

@section('content')
    <main class="content-page content-page--{{ $page->slug }}">
        {{-- The reference guides open with the back link to /navy-reference/ instead
             of a breadcrumb trail; every other content page keeps the breadcrumb. --}}
        @if ($page->shows_reference_backlink)
            @include('partials.trust.back-link')
        @elseif ($showCrumbs ?? true)
            <nav class="breadcrumb" aria-label="Breadcrumb">
                @foreach ($crumbs as $i => $crumb)
                    @if ($i === count($crumbs) - 1)
                        <span aria-current="page">{{ $crumb['name'] }}</span>
                    @else
                        <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                        <span aria-hidden="true">/</span>
                    @endif
                @endforeach
            </nav>
        @endif

        @if ($disclosure !== null)
            @include('partials.trust.disclosure', ['disclosure' => $disclosure])
        @endif

        <article>
            <div class="content-hero">
                @if (filled($page->eyebrow))
                    <div class="content-eyebrow">{{ $page->eyebrow }}</div>
                @endif

                <h1>{{ $page->h1 ?? $heading }}</h1>

                @if ($heroLead !== null)
                    <p class="{{ $paragraphClass($heroLead) ?: 'content-lead' }}">{!! isset($heroLead['spans']) ? $inline($heroLead['spans']) : e($plain($heroLead)) !!}</p>
                @endif

                @foreach ($heroBlocks as $heroBlock)
                    <p class="{{ $paragraphClass($heroBlock) }}">{!! isset($heroBlock['spans']) ? $inline($heroBlock['spans']) : e($plain($heroBlock)) !!}</p>
                @endforeach

                {{-- The credit-cards guide uses the legacy `TrustByline` variant that
                     leads with the publish date (BestCreditCardsForMilitary.tsx:529);
                     the other guides carry their own "last reviewed / sources checked"
                     line. --}}
                @if ($page->slug === 'our-process')
                    {{-- /our-process/ has its own byline entirely (OurProcess.tsx:120-142):
                         a centred three-up strip with upper-case role labels, short
                         credentials and a green "facts verified" pill — no portraits and
                         no dates line. Credentials come from the page's own columns. --}}
                    <div class="op-byline">
                        @foreach ([['WRITTEN BY', $page->author, $page->author_credentials], ['REVIEWED BY', $page->reviewer, $page->reviewer_credentials]] as [$role, $person, $credentials])
                            @if ($person)
                                <div>
                                    <div class="op-byline-role">{{ $role }}</div>
                                    <a class="op-byline-link" href="/authors/{{ $person->slug }}/">
                                        <div class="op-byline-name">{{ $person->name }}</div>
                                    </a>
                                    <div class="op-byline-cred">{{ $credentials ?? $person->credentials }}</div>
                                </div>
                            @endif
                        @endforeach
                        @if ($page->last_reviewed)
                            <div>
                                <div class="op-byline-role">FACTS VERIFIED</div>
                                <div class="op-byline-pill"><span></span>{{ strtoupper($page->last_reviewed->format('M j, Y')) }}</div>
                            </div>
                        @endif
                    </div>
                @elseif ($showsByline)
                    @include('partials.trust.byline', ['publishDate' => $page->slug === 'best-credit-cards-for-military'])
                @endif
            </div>

            {{-- Renders only when `key_facts` carries fact rows. --}}
            @include('partials.trust.key-facts')

            @if (empty($keyFacts['facts'] ?? []) && $keyFactsTitle !== null)
                <section class="key-facts" aria-label="{{ $keyFactsTitle }}" data-llm-key-facts="1">
                    <h2>{{ $keyFactsTitle }}</h2>
                    @if ($keyFactsSource !== null)
                        <p class="key-facts-source">{{ $keyFactsSource }}</p>
                    @endif
                </section>
            @endif

            @foreach ($items as $i => $item)
                @php $block = $item['block'] ?? []; @endphp
                @switch($item['type'])
                    @case('heading')
                        @php
                            $headingText = $plain($block);
                            $headingKey = mb_strtolower(trim($headingText));
                            // `level` (2 or 3) mirrors the source page's heading depth;
                            // older blocks without it stay h2.
                            $level = (int) ($block['level'] ?? 2) === 3 ? 'h3' : 'h2';
                        @endphp
                        @if (! $faqInline && isset($faqAnswers[$headingKey]))
                            <div class="content-faq-item">
                                <h3>{{ $headingText }}</h3>
                                <p>{{ $faqAnswers[$headingKey] }}</p>
                            </div>
                        @else
                            <{{ $level }}>{{ $headingText }}</{{ $level }}>
                            @if (! $faqInline && $faqRows !== [] && $headingKey === 'frequently asked questions')
                                <div class="content-faq">
                                    @foreach ($faqRows as $faqRow)
                                        <details>
                                            <summary>{{ $faqRow['question'] }}</summary>
                                            <p>{{ $faqRow['answer'] }}</p>
                                        </details>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                        @break

                    @case('faq_item')
                        {{-- Two legacy treatments: an expanded question/answer pair
                             (the VA guides) or a closed <details> card. --}}
                        @if ($block['collapsed'] ?? false)
                            <details class="content-faq-card">
                                <summary>{{ $block['question'] ?? '' }}</summary>
                                <p>{!! $inline($block['spans'] ?? []) !!}</p>
                            </details>
                        @else
                            <div class="content-faq-item">
                                <h3>{{ $block['question'] ?? '' }}</h3>
                                <p>{!! $inline($block['spans'] ?? []) !!}</p>
                            </div>
                        @endif
                        @break

                    @case('list_item')
                        @if ($i === $tocIndex)
                            <nav class="content-toc" aria-label="On this page">
                                <div class="content-toc-label">On this page</div>
                                <ul>
                                    @foreach ($item['items'] as $entry)
                                        <li><span>{{ $entry }}</span></li>
                                    @endforeach
                                </ul>
                            </nav>
                        @else
                            <ul class="content-list">
                                @foreach ($item['items'] as $entry)
                                    <li>{{ $entry }}</li>
                                @endforeach
                            </ul>
                        @endif
                        @break

                    @case('list')
                        @php $tag = ($block['ordered'] ?? false) ? 'ol' : 'ul'; @endphp
                        <{{ $tag }} class="content-list">
                            @foreach (($block['items'] ?? []) as $entry)
                                <li>{!! is_array($entry) ? $inline($entry['spans'] ?? []) : e((string) $entry) !!}</li>
                            @endforeach
                        </{{ $tag }}>
                        @break

                    @case('callout')
                        <div @class(['content-callout', 'content-callout--alert' => ($block['variant'] ?? null) === 'alert'])>{!! $inline($block['spans'] ?? []) !!}</div>
                        @break

                    @case('toc')
                        <nav class="content-toc" aria-label="On this page">
                            <div class="content-toc-label">{{ $block['label'] ?? 'On this page' }}</div>
                            <ul>
                                @foreach (($block['items'] ?? []) as $entry)
                                    <li><a href="{{ LinkUrl::sanitize((string) ($entry['url'] ?? '#')) }}">{{ $entry['label'] ?? '' }}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                        @break

                    @case('jump_nav')
                        <nav class="content-jump" aria-label="On this page">
                            <span class="content-jump-label">{{ $block['label'] ?? 'On this page:' }}</span>
                            @foreach (($block['items'] ?? []) as $entry)
                                <a href="{{ LinkUrl::sanitize((string) ($entry['url'] ?? '#')) }}">{{ $entry['label'] ?? '' }}</a>
                            @endforeach
                        </nav>
                        @break

                    @case('table')
                        @php $panel = ($block['variant'] ?? 'plain') === 'panel'; @endphp
                        <div class="{{ $panel ? 'content-tablepanel' : 'content-tablewrap' }}"
                             @if ($panel) role="region" aria-label="{{ $block['label'] ?? '' }}" tabindex="0" @endif>
                            <table class="content-table">
                                @if (filled($block['caption'] ?? null))
                                    <caption>{{ $block['caption'] }}</caption>
                                @endif
                                <thead>
                                    <tr>
                                        @foreach (($block['columns'] ?? []) as $column)
                                            <th scope="col" @class(['is-right' => ($column['align'] ?? '') === 'right'])>{{ $column['label'] ?? '' }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (($block['rows'] ?? []) as $row)
                                        <tr>
                                            @foreach ($row as $cell)
                                                <td @class(['is-right' => ($cell['align'] ?? '') === 'right', 'is-accent' => $cell['accent'] ?? false])
                                                    @if (filled($cell['label'] ?? null)) data-label="{{ $cell['label'] }}" @endif>{!! $inline($cell['spans'] ?? []) !!}@if (filled($cell['sub'] ?? null))<div class="content-cell-sub">{{ $cell['sub'] }}</div>@endif</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @break

                    @case('card')
                        <article class="content-card" @if (filled($block['anchor'] ?? null)) id="{{ $block['anchor'] }}" @endif>
                            @if (filled($block['eyebrow'] ?? null))
                                <div class="content-card-eyebrow">{{ $block['eyebrow'] }}</div>
                            @endif
                            <h3>{{ $block['title'] ?? '' }}</h3>
                            @if (($block['meta'] ?? []) !== [])
                                <div class="content-card-meta">
                                    @foreach ($block['meta'] as $meta)
                                        <span>{!! $inline($meta['spans'] ?? []) !!}</span>
                                    @endforeach
                                </div>
                            @endif
                            @if (filled($block['note'] ?? null))
                                <div class="content-card-note">{{ $block['note'] }}</div>
                            @endif
                            <div class="content-card-body">
                                @foreach (($block['body'] ?? []) as $paragraph)
                                    <p>{!! $inline($paragraph['spans'] ?? []) !!}</p>
                                @endforeach
                            </div>
                            @if (filled($block['cta']['url'] ?? null))
                                <div class="content-card-cta">
                                    <a href="{{ LinkUrl::sanitize((string) $block['cta']['url']) }}" target="_blank" rel="noopener noreferrer sponsored">{{ $block['cta']['label'] ?? '' }}</a>
                                </div>
                            @endif
                        </article>
                        @break

                    @case('link_card')
                        <a class="content-linkcard" href="{{ LinkUrl::sanitize((string) ($block['url'] ?? '#')) }}"
                           @if (($block['icon'] ?? '') === 'external') target="_blank" rel="noopener noreferrer" @endif>
                            <span class="content-linkcard-icon" aria-hidden="true">
                                @if (($block['icon'] ?? '') === 'mail')
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                                @else
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
                                @endif
                            </span>
                            <span>
                                <span class="content-linkcard-label">{{ $block['label'] ?? '' }}</span>
                                <span class="content-linkcard-value">{{ $block['value'] ?? '' }}</span>
                            </span>
                        </a>
                        @break

                    @case('info_card')
                        <div class="content-infocard">
                            <div class="content-infocard-label">{{ $block['label'] ?? '' }}</div>
                            <p>{!! $inline($block['spans'] ?? []) !!}</p>
                        </div>
                        @break

                    {{-- /our-process/ only: the editorial-standards layout (full-bleed
                         bands, step cards, the source-authority ladder, the publish
                         gate, the refusals list and the corrections CTA). --}}
                    @case('band')
                        <section class="op-band op-band--{{ $block['tone'] ?? 'light' }}">
                            <div class="op-band-inner">
                                <div class="op-eyebrow">{{ $block['eyebrow'] ?? '' }}</div>
                                <h2>{{ $block['heading'] ?? '' }}</h2>
                                @if (filled($block['lead'] ?? null))
                                    <p class="op-band-lead">{{ $block['lead'] }}</p>
                                @endif
                                <div class="op-grid op-grid--{{ $block['layout'] ?? 'steps' }}">
                                    @foreach (($block['cards'] ?? []) as $card)
                                        <div class="op-card">
                                            @if (filled($card['n'] ?? null))
                                                <div class="op-card-n">{{ $card['n'] }}</div>
                                            @endif
                                            <div class="op-card-title">{{ $card['title'] ?? '' }}</div>
                                            <p>{{ $card['desc'] ?? '' }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </section>
                        @break

                    @case('step')
                        <div class="op-step">
                            <div class="op-tag">{{ $block['tag'] ?? '' }}</div>
                            <h2>{{ $block['heading'] ?? '' }}</h2>
                            <p>{!! $inline($block['spans'] ?? []) !!}</p>
                        </div>
                        @break

                    @case('ladder')
                        <div class="op-step op-ladder">
                            <div class="op-tag">{{ $block['tag'] ?? '' }}</div>
                            <h2>{{ $block['heading'] ?? '' }}</h2>
                            <div class="op-ladder-rows">
                                @foreach (($block['rows'] ?? []) as $row)
                                    <div class="op-ladder-row op-ladder-row--{{ $row['tone'] ?? 1 }}">
                                        <div class="op-ladder-n">{{ $row['n'] ?? '' }}</div>
                                        <div>
                                            <div class="op-ladder-title">{{ $row['title'] ?? '' }}</div>
                                            <p>{{ $row['desc'] ?? '' }}</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <p class="op-ladder-note">{{ $block['note'] ?? '' }}</p>
                        </div>
                        @break

                    @case('refusals')
                        <div class="op-step op-refusals">
                            <div class="op-tag op-tag--red">{{ $block['tag'] ?? '' }}</div>
                            <h2>{{ $block['heading'] ?? '' }}</h2>
                            <p class="op-refusals-lead">{{ $block['lead'] ?? '' }}</p>
                            <div class="op-refusals-rows">
                                @foreach (($block['items'] ?? []) as $entry)
                                    <div class="op-refusals-row">
                                        <div class="op-refusals-mark" aria-hidden="true">✕</div>
                                        <p>{{ $entry }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @break

                    @case('freshness')
                        <div class="op-freshness">
                            <div class="op-freshness-copy">
                                <div class="op-freshness-label">{{ $block['label'] ?? '' }}</div>
                                <p>{!! $inline($block['spans'] ?? []) !!}</p>
                            </div>
                            <div class="op-freshness-stat">
                                <div class="op-freshness-n">{{ $block['stat'] ?? '' }}</div>
                                <div class="op-freshness-unit">{{ $block['statLabel'] ?? '' }}</div>
                            </div>
                        </div>
                        @break

                    @case('rule_note')
                        <div class="op-rulenote">
                            <h3>{{ $block['heading'] ?? '' }}</h3>
                            <p>{!! $inline($block['spans'] ?? []) !!}</p>
                        </div>
                        @break

                    @case('cta_panel')
                        <div class="op-cta">
                            <h3>{{ $block['heading'] ?? '' }}</h3>
                            <p>{!! $inline($block['spans'] ?? []) !!}</p>
                            <div class="op-cta-actions">
                                @foreach (($block['actions'] ?? []) as $j => $action)
                                    <a class="op-btn op-btn--{{ $j === 0 ? 'gold' : 'ghost' }}" href="{{ LinkUrl::sanitize((string) ($action['url'] ?? '#')) }}">{{ $action['label'] ?? '' }}</a>
                                @endforeach
                            </div>
                        </div>
                        @break

                    @case('note')
                        <aside class="note">{{ $block['text'] ?? '' }}</aside>
                        @break

                    @default
                        {{-- The paragraph that opens the body is the legacy lead
                             (larger, off-white); the rest are section body copy. --}}
                        <p class="{{ $paragraphClass($block) ?: ($i === 0 ? 'content-lead' : '') }}">{!! isset($block['spans']) ? $inline($block['spans']) : e($plain($block)) !!}</p>
                @endswitch
            @endforeach
        </article>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
