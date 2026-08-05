@extends('layouts.base')

{{-- Generic DB-driven content page. Renders the CMS-editable `pages.body_blocks`
     (an ordered list of typed blocks) plus the trust chrome the legacy reference
     pages carry: the "← Navy Reference" back link, the page-specific independence
     disclosure, the hero eyebrow, the KeyFacts card and the FAQ answers. The
     head/JSON-LD is byte-locked by SeoHead + ContentPageSchema. Shared by the
     editorial content pages (privacy/terms/contact, our-process, the credit-cards
     guide, and the veterans-day / va-disability / veterans-home-care guides).

     Presentation is ported from the legacy inline styles in
     src/page-views/{VaDisability,VeteransDay,VeteransHomeCare,BestCreditCardsForMilitary,OurProcess}.tsx
     — see resources/css/families/ymyl.css. Blocks arrive flat, so the groupings the
     legacy markup has are rebuilt here: consecutive bullets fold into one list, the
     jump list ("On this page") is the first bullet run that still precedes every
     section heading, and a body heading that IS an FAQ question is paired back up
     with its answer from the page's `faqs` relation. --}}
@php
    /** @var list<array{name: string, url: string}> $crumbs */
    /** @var list<array<string, mixed>> $blocks */
    /** @var string $heading */
    /** @var iterable<int, \App\Domain\Shared\Models\Faq> $faqs */

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

    if (($items[0]['type'] ?? null) === 'heading') {
        $keyFactsTitle = (string) ($items[0]['block']['text'] ?? '');
        array_shift($items);

        $next = (string) ($items[0]['block']['text'] ?? '');
        if (($items[0]['type'] ?? null) === 'paragraph' && str_starts_with($next, 'Source:')) {
            $keyFactsSource = $next;
            array_shift($items);
        }
    } elseif (($items[0]['type'] ?? null) === 'paragraph') {
        $heroLead = (string) ($items[0]['block']['text'] ?? '');
        array_shift($items);
    }

    // The disclosure body is `pages.disclosure` (the wording is page-specific: the
    // VA guides name the VA, the credit-cards guide carries the FTC advertiser
    // language). On pages whose disclosure also landed in `body_blocks` as the
    // opening paragraph ("Advertiser & editorial disclosure: …"), drop that block
    // so the box can't render twice.
    $disclosure = $page->disclosure;
    if ($heroLead !== null && preg_match('/^.{0,60}?disclosure:/i', $heroLead) === 1) {
        $disclosure ??= $heroLead;
        $heroLead = null;
    }

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
        if ($item['type'] === 'heading' && isset($faqAnswers[mb_strtolower(trim((string) ($item['block']['text'] ?? '')))])) {
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
        @else
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
                    <p class="content-lead">{{ $heroLead }}</p>
                @endif

                @if ($showsByline)
                    @include('partials.trust.byline')
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
                @switch($item['type'])
                    @case('heading')
                        @php
                            $headingText = (string) ($item['block']['text'] ?? '');
                            $headingKey = mb_strtolower(trim($headingText));
                            // `level` (2 or 3) mirrors the source page's heading depth;
                            // older blocks without it stay h2.
                            $level = (int) ($item['block']['level'] ?? 2) === 3 ? 'h3' : 'h2';
                        @endphp
                        @if (isset($faqAnswers[$headingKey]))
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
                        <ul class="content-list">
                            @foreach (($item['block']['items'] ?? []) as $entry)
                                <li>{{ $entry }}</li>
                            @endforeach
                        </ul>
                        @break
                    @case('note')
                        <aside class="note">{{ $item['block']['text'] ?? '' }}</aside>
                        @break
                    @default
                        {{-- The paragraph that opens the body is the legacy lead
                             (larger, off-white); the rest are section body copy. --}}
                        <p @class(['content-lead' => $i === 0])>{{ $item['block']['text'] ?? '' }}</p>
                @endswitch
            @endforeach
        </article>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
