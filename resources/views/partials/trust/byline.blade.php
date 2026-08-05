{{-- Author + reviewer byline — ported 1:1 from TrustByline in the legacy
     src/components/ReferenceTrust.tsx. Author/reviewer come from the page's
     assigned users (CMS), dates from the page's trust columns.

     $page is required. When $page->date_published is passed through as
     $publishDate the dates line renders "Publish date · Last reviewed" and drops
     "Sources checked" (the legacy discount-page variant). --}}
@php
    $author = $page->author;
    $reviewer = $page->reviewer;
    $fmt = static fn ($d): ?string => $d?->format('F j, Y');
    $lastReviewed = $fmt($page->last_reviewed) ?? $fmt($page->date_modified);
    $sourcesChecked = $fmt($page->sources_checked) ?? $lastReviewed;
    $publishDate = ($publishDate ?? false) ? $fmt($page->date_published) : null;
    $processNewTab = $processLinkNewTab ?? false;
@endphp
@if ($author || $reviewer)
    <div class="trust-byline">
        <div class="trust-byline-col">
        @if ($author)
            <div class="trust-byline-role">Written by</div>
            <div class="trust-byline-person">
                @if ($author->avatar_path)
                    <img src="{{ $author->avatar_path }}" alt="Portrait of {{ $author->name }}" width="56" height="56" loading="lazy">
                @endif
                <div>
                    <a class="trust-byline-name" href="/authors/{{ $author->slug }}/">{{ $author->name }}</a>
                    @if ($author->credentials)
                        <span class="trust-byline-cred"> — {{ $author->credentials }}</span>
                    @endif
                </div>
            </div>
        @endif

        {{-- Some pages are author-only: VeteransDay.tsx renders no reviewer row.
             The flag (not a null reviewer_id) carries that intent, because
             EditorialTeamSeeder back-fills the default byline onto any page whose
             reviewer is null. --}}
        @if ($reviewer && $page->shows_reviewer)
            <div class="trust-byline-role">Reviewed by</div>
            <div class="trust-byline-person is-reviewer">
                @if ($reviewer->avatar_path)
                    <img src="{{ $reviewer->avatar_path }}" alt="Portrait of {{ $reviewer->name }}" width="56" height="56" loading="lazy">
                @endif
                <div>
                    <a class="trust-byline-name is-reviewer" href="/authors/{{ $reviewer->slug }}/">{{ $reviewer->name }}</a>
                    @if ($reviewer->credentials)
                        <span class="trust-byline-cred"> — {{ $reviewer->credentials }}</span>
                    @endif
                </div>
            </div>
        @endif

        @if ($lastReviewed)
            <div class="trust-byline-dates">
                @if ($publishDate)
                    Publish date: <span>{{ $publishDate }}</span> · Last reviewed: <span>{{ $lastReviewed }}</span>
                @else
                    Last reviewed: <span>{{ $lastReviewed }}</span> · Sources checked: <span>{{ $sourcesChecked }}</span>
                @endif
            </div>
        @endif

        {{-- The reference pages close the byline with this row; the YMYL guides end
             at the dates line (VaDisability.tsx and the other three stop there). --}}
        @if ($page->shows_process_link)
            <div class="trust-byline-process">
                How we research &amp; review:
                <a href="/our-process/" @if ($processNewTab) target="_blank" rel="noopener noreferrer" @endif>Our editorial process</a>
            </div>
        @endif
        </div>
    </div>
@endif
