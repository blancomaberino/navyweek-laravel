@extends('layouts.base')

{{-- /navy-designators/{community}/ — one officer community's designators. --}}
@section('content')
    <main class="designator-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::root('designators') }}">Navy Designators</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $communityLabel }}</span>
        </nav>

        <header class="designator-hero">
            <h1>{{ $page->h1 ?? $page->title }}</h1>
            <p class="intro">{{ $page->meta_description }}</p>
        </header>

        @include('partials.designator-card-list', ['designators' => $designators])
    </main>
@endsection
