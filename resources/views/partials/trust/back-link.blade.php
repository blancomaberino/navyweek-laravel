{{-- "← Navy Reference" back link — ported 1:1 from the legacy
     src/components/NavyReferenceBackLink.tsx. Rendered only for pages that opt
     in via the `shows_reference_backlink` CMS flag. --}}
@if ($page->shows_reference_backlink)
    <div class="reference-backlink">
        <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('navy_reference') }}">&larr; Navy Reference</a>
    </div>
@endif
