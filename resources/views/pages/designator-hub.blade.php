@extends('layouts.base')

{{-- /navy-designators/ — every officer designator grouped by community. --}}
@section('content')
    <main class="designator-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Navy Designators</span>
        </nav>

        @include('partials.trust.disclosure')

        <header class="designator-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.trust.byline')
        @include('partials.trust.key-facts')

        @foreach ($byCommunity as $communityLabel => $designators)
            <section class="designator-group" aria-label="{{ $communityLabel }}">
                <h2>{{ mb_strtoupper($communityLabel) }}</h2>
                @include('partials.designator-card-list', ['designators' => $designators])
            </section>
        @endforeach

        @include('partials.trust.editorial-policy')
    </main>
@endsection
