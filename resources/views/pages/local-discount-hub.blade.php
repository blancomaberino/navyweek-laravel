@extends('layouts.base')

{{-- Local-discount rollup hub — the /discounts/ root (states), a /discounts/{state}/
     (cities), or a /discounts/{state}/{city}/ (businesses) index. Ported from the legacy
     src/page-views/LocalDiscountHubs.tsx (`lh-*` vocabulary); styles live in
     resources/css/families/local-discount.css. The controller passes the breadcrumb chain
     + the link list; the head/JSON-LD is byte-locked by SeoHead + LocalDiscountHubSchema. --}}
@php
    /** @var list<array{name: string, url: string}> $crumbs */
    /** @var string $eyebrow */
    /** @var string $headingLead */
    /** @var string $headingAccent */
    /** @var string $intro */
    /** @var string $note */
    /** @var list<array{url: string, name: string, sub: string, meta: string, go: string}> $items */
@endphp

@section('content')
    <main class="lh-page lh-wrap">
        <nav class="lh-crumb" aria-label="Breadcrumb">
            @foreach ($crumbs as $i => $crumb)
                @if ($i === count($crumbs) - 1)
                    <span class="here">{{ $crumb['name'] }}</span>
                @else
                    <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a><span class="sep">›</span>
                @endif
            @endforeach
        </nav>

        <header class="lh-hero">
            <p class="lh-eyebrow">{{ $eyebrow }}</p>
            <h1 class="lh-h1">{{ $headingLead }}<em>{{ $headingAccent }}</em></h1>
            <p class="lh-intro">{{ $intro }}</p>
        </header>

        @if ($items === [])
            <p class="lh-intro">No local discounts are listed here yet.</p>
        @else
            <div class="lh-grid">
                @foreach ($items as $item)
                    <a class="lh-card" href="{{ $item['url'] }}">
                        <div class="nm">{{ $item['name'] }}</div>
                        <div class="sub">{{ $item['sub'] }}</div>
                        <div class="meta">{{ $item['meta'] }}</div>
                        <div class="go">{{ $item['go'] }}</div>
                    </a>
                @endforeach
            </div>
        @endif

        <p class="lh-note">{{ $note }}</p>
    </main>
@endsection
