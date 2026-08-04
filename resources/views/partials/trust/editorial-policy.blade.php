{{-- "Editorial policy" box + "Report an outdated fact" corrections link — ported
     1:1 from EditorialPolicy + CorrectionsBox in the legacy ReferenceTrust.tsx.
     The two page-specific bullets (source priority, review cadence) and the
     mailto subject label come from the page's CMS columns; the other four
     bullets are fixed policy wording. Renders only when the page opts in by
     having a source priority set. --}}
@php
    $sourcePriority = $page->editorial_source_priority;
    $reviewCadence = $page->editorial_review_cadence;
    $pageLabel = $page->trust_page_label ?? $page->title;
    $mailto = 'mailto:hello@navyweek.org?subject='.rawurlencode('Report an outdated fact: '.$pageLabel);
@endphp
@if ($sourcePriority)
    <section id="editorial-policy" class="editorial-policy" aria-label="Editorial policy">
        <h2>Editorial policy</h2>
        <ul>
            <li><strong>Source priority.</strong> {{ $sourcePriority }}</li>
            <li><strong>Independence.</strong> NavyWeek.org is not affiliated with the U.S. Navy, the Department of Defense, NAVCO, or any federal agency. We do not accept payment to recommend specific recruiters, schools, vendors, or services.</li>
            @if ($reviewCadence)
                <li><strong>Review cadence.</strong> {{ $reviewCadence }}</li>
            @endif
            <li><strong>Reviewer.</strong> The page is reviewed for accuracy by the reviewer named in the byline. The "Last reviewed" date at the top of the page reflects the most recent review pass.</li>
            <li><strong>Corrections.</strong> Factual errors are corrected as soon as we can verify the issue against an official source. See the "Report an outdated fact" link below.</li>
            <li><strong>Not advice.</strong> This page is informational only. For decisions about service, benefits, pay, or assignment, rely on official .mil sources and your chain of command, detailer, recruiter, or accredited representative.</li>
        </ul>
    </section>

    <section class="corrections-box" aria-label="Report an outdated fact">
        <strong>See something out of date?</strong>
        <a href="{{ $mailto }}">Report an outdated fact</a>
        or reach the editors via the <a href="/contact/">contact page</a>. Please include a link to the official .gov or .mil source you believe is more current.
    </section>
@endif
