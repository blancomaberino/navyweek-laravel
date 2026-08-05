@extends('layouts.base')

{{-- Generic DB-driven content page. Renders the CMS-editable `pages.body_blocks`
     (an ordered list of typed blocks) beneath a breadcrumb. The head/JSON-LD is
     byte-locked by SeoHead + ContentPageSchema. Shared by the editorial content pages
     (privacy/terms/contact now; veterans-day / va-disability / veterans-home-care as
     their richer schema slices land). --}}
@php
    /** @var list<array{name: string, url: string}> $crumbs */
    /** @var list<array<string, mixed>> $blocks */
    /** @var string $heading */
@endphp

@section('content')
    <main class="content-page">
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

        <article>
            <h1>{{ $page->h1 ?? $heading }}</h1>

            @foreach ($blocks as $block)
                @switch($block['type'] ?? 'paragraph')
                    @case('heading')
                        {{-- `level` (2 or 3) mirrors the source page's heading depth;
                             older blocks without it stay h2. --}}
                        @php($level = (int) ($block['level'] ?? 2) === 3 ? 'h3' : 'h2')
                        <{{ $level }}>{{ $block['text'] ?? '' }}</{{ $level }}>
                        @break
                    @case('list_item')
                        <ul class="content-list"><li>{{ $block['text'] ?? '' }}</li></ul>
                        @break
                    @case('list')
                        <ul>
                            @foreach (($block['items'] ?? []) as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        @break
                    @case('note')
                        <aside class="note">{{ $block['text'] ?? '' }}</aside>
                        @break
                    @default
                        <p>{{ $block['text'] ?? '' }}</p>
                @endswitch
            @endforeach
        </article>

        @include('partials.trust.editorial-policy')
    </main>
@endsection
