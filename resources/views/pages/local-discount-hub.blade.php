@extends('layouts.base')

{{-- Local-discount rollup hub — the /discounts/ root (states), a /discounts/{state}/
     (cities), or a /discounts/{state}/{city}/ (businesses) index. The controller passes
     the breadcrumb chain + the link list; the head/JSON-LD is byte-locked by SeoHead +
     LocalDiscountHubSchema. --}}
@php
    /** @var list<array{name: string, url: string}> $crumbs */
    /** @var string $heading */
    /** @var list<array{url: string, name: string, meta: string|null}> $items */
@endphp

@section('content')
    <main class="local-discount-hub">
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

        <header>
            <h1>{{ $heading }}</h1>
            <p>{{ $page->meta_description }}</p>
        </header>

        @if ($items === [])
            <p>No local discounts are listed here yet.</p>
        @else
            <ul class="hub-list">
                @foreach ($items as $item)
                    <li>
                        <a href="{{ $item['url'] }}">{{ $item['name'] }}</a>
                        @if (! empty($item['meta'])) <span class="meta">{{ $item['meta'] }}</span>@endif
                    </li>
                @endforeach
            </ul>
        @endif
        @include('partials.trust.editorial-policy')
    </main>
@endsection
