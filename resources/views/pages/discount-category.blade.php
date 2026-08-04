@extends('layouts.base')

{{-- Discount category hub. The ordered grid of live brands in one category
     (DiscountCategory + the repository's orderedConnections). NavyWeek is an
     independent publisher; the disclosure is emitted first per the YMYL policy. --}}
@section('content')
    <main class="discount-category">
        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="/discount/">Military Discounts</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $category->name }}</span>
        </nav>

        <p class="independence-disclosure" role="note">
            NavyWeek.org is an independent editorial publisher and is
            <strong>not affiliated</strong> with the brands listed below or the U.S. Navy.
            We may earn a commission from links on this page.
        </p>

        <header class="category-hero">
            <p class="eyebrow">// Military &amp; Veteran Savings</p>
            <h1>{{ $category->h1 }}</h1>
            @foreach ($category->intro as $paragraph)
                <p class="intro {{ $loop->first ? 'intro-lead' : '' }}">{{ $paragraph }}</p>
            @endforeach
            @if ($category->last_verified !== '')
                <p class="byline">Last verified {{ $category->last_verified }}</p>
            @endif
        </header>

        <section class="brand-grid-section" aria-label="{{ $category->name }}">
            {{-- Heading is the bare category name, matching the legacy hub (the live
                 site shows no brand count here). --}}
            <h2>{{ $category->name }}</h2>

            @if ($brands->isEmpty())
                <p class="empty-state">
                    No brands in this category yet — <a href="/discount/">browse all military discounts</a>.
                </p>
            @else
                <ul class="brand-grid">
                    @foreach ($brands as $brand)
                        <li class="brand-card">
                            <a href="{{ $brand['url'] }}">
                                @if ($brand['logo_url'])
                                    <img class="brand-logo" src="{{ $brand['logo_url'] }}" alt="{{ $brand['brand'] }} logo" loading="lazy">
                                @endif
                                <span class="brand-name">{{ $brand['brand'] }}</span>
                                <span class="view-link" aria-hidden="true">View discount →</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <footer class="category-footer">
            <a href="/discount/">← All military discounts</a>
        </footer>
        @include('partials.trust.editorial-policy')
    </main>
@endsection
