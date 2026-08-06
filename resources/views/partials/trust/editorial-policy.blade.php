{{-- "Editorial policy" box + "Report an outdated fact" corrections link — ported
     1:1 from EditorialPolicy + CorrectionsBox in the legacy ReferenceTrust.tsx.

     ALL SIX bullets are page-specific in the legacy, not two: the reference pages
     share the generic wording kept below as the fallback, but each YMYL guide
     ships its own EditorialPolicyBox() — the VA guide's Independence bullet names
     the VA and disclaims paid attorney/VSO placement, and its Reviewer bullet
     carries the "not a VA-accredited representative" disclaimer, which is
     load-bearing on a benefits page. So every bullet reads from a nullable CMS
     column and falls back to the house wording. Renders only when the page opts
     in by having a source priority set. --}}
@php
    $sourcePriority = $page->editorial_source_priority;
    $reviewCadence = $page->editorial_review_cadence;
    $pageLabel = $page->trust_page_label ?? $page->title;
    $mailto = 'mailto:hello@navyweek.org?subject='.rawurlencode('Report an outdated fact: '.$pageLabel);

    $independence = $page->editorial_independence
        ?? 'NavyWeek.org is not affiliated with the U.S. Navy, the Department of Defense, NAVCO, or any federal agency. We do not accept payment to recommend specific recruiters, schools, vendors, or services.';
    $reviewerNote = $page->editorial_reviewer_note
        ?? 'The page is reviewed for accuracy by the reviewer named in the byline. The "Last reviewed" date at the top of the page reflects the most recent review pass.';
    $corrections = $page->editorial_corrections
        ?? 'Factual errors are corrected as soon as we can verify the issue against an official source. See the "Report an outdated fact" link below.';
    $notAdvice = $page->editorial_not_advice
        ?? 'This page is informational only. For decisions about service, benefits, pay, or assignment, rely on official .mil sources and your chain of command, detailer, recruiter, or accredited representative.';
    $correctionsNote = $page->corrections_note
        ?? 'Please include a link to the official .gov or .mil source you believe is more current.';
@endphp
@if ($sourcePriority)
    <section id="editorial-policy" class="editorial-policy" aria-label="Editorial policy">
        <h2>Editorial policy</h2>
        <ul>
            <li><strong>Source priority.</strong> {{ $sourcePriority }}</li>
            <li><strong>Independence.</strong> {{ $independence }}</li>
            @if ($reviewCadence)
                <li><strong>Review cadence.</strong> {{ $reviewCadence }}</li>
            @endif
            <li><strong>Reviewer.</strong> {{ $reviewerNote }}</li>
            <li><strong>Corrections.</strong> {{ $corrections }}</li>
            <li><strong>Not advice.</strong> {{ $notAdvice }}</li>
        </ul>
    </section>

    <section class="corrections-box" aria-label="Report an outdated fact">
        <strong>See something out of date?</strong>
        <a href="{{ $mailto }}">Report an outdated fact</a>
        or reach the editors via the <a href="/contact/">contact page</a>. {{ $correctionsNote }}
    </section>
@endif
