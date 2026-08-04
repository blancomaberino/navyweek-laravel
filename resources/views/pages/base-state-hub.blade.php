@extends('layouts.base')

{{-- /navy-bases/{state}/ — every installation in one US state, grouped by base
     type ("Naval Stations (3)"), matching the legacy NavyBaseState view. --}}
@section('content')
    <main class="base-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('bases') }}">Navy Bases</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $regionName }}</span>
        </nav>

        <header class="base-hub-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.key-facts')

        @foreach ($grouped as $typeLabel => $bases)
            <section class="base-hub-group" aria-label="{{ $typeLabel }}">
                <h2>{{ $typeLabel }} ({{ $bases->count() }})</h2>
                @include('partials.base-card-list', ['bases' => $bases])
            </section>
        @endforeach

    </main>
@endsection
