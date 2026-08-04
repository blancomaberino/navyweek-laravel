{{-- Independence disclosure — ported 1:1 from TrustDisclosure in the legacy
     src/components/ReferenceTrust.tsx. Body text is overridable per page via
     $disclosure; otherwise the standard reference-page wording. --}}
<section class="trust-disclosure" aria-label="Independence and editorial disclosure">
    <div class="trust-disclosure-label">Disclosure</div>
    <p>{{ $disclosure ?? 'NavyWeek.org is an independent publication. We are not affiliated with, endorsed by, or sponsored by the U.S. Navy, the U.S. Department of Defense, NAVCO, or DFAS. This page is informational reference material — not official guidance — and is compiled from public U.S. government sources.' }}</p>
</section>
