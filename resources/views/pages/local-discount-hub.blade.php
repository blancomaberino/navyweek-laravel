@extends('layouts.base')

{{-- Local-discount rollup hub — the /discounts/ root (states), a /discounts/{state}/
     (cities), or a /discounts/{state}/{city}/ (businesses) index. Ported from the legacy
     src/page-views/LocalDiscountHubs.tsx (`lh-*` vocabulary); styles live in
     resources/css/families/local-discount.css. The controller passes the breadcrumb chain
     + the link list; the head/JSON-LD is byte-locked by SeoHead + LocalDiscountHubSchema. --}}
@php
    /** @var list<array{name: string, url: string}> $crumbs */
    /** @var string $heading */
    /** @var list<array{url: string, name: string, meta: string|null}> $items */

    // The three hub levels are distinguished by breadcrumb depth: Home + Local Discounts
    // (root), + state, + city. The legacy view is one component per level.
    $level = match (count($crumbs)) {
        2 => 'root',
        3 => 'state',
        default => 'city',
    };
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
            @if ($level === 'root')
                <p class="lh-eyebrow">Local businesses · by state &amp; city</p>
            @endif
            <h1 class="lh-h1">{{ $heading }}</h1>
            <p class="lh-intro">{{ $page->meta_description }}</p>
        </header>

        @if ($items === [])
            <p class="lh-intro">No local discounts are listed here yet.</p>
        @else
            <div class="lh-grid">
                @foreach ($items as $item)
                    <a class="lh-card" href="{{ $item['url'] }}">
                        <div class="nm">{{ $item['name'] }}</div>
                        @if (! empty($item['meta']))
                            <div class="meta">{{ $item['meta'] }}</div>
                        @endif
                        <div class="go">{{ $level === 'city' ? 'See the discount →' : 'Browse '.$item['name'].' →' }}</div>
                    </a>
                @endforeach
            </div>
        @endif

        <p class="lh-note">
            @if ($level === 'root')
                New cities and businesses are added deliberately as each offer is verified. NavyWeek.org is an
                independent publisher and is not affiliated with the businesses listed here.
            @else
                NavyWeek.org is an independent publisher and is not affiliated with the businesses listed here.
            @endif
        </p>
    </main>
@endsection
