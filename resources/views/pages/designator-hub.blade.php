@extends('layouts.base')

{{-- /navy-designators/ — the officer-designator hub. A 1:1 port of the legacy
     src/page-views/NavyDesignatorsHub.tsx: the community cards, then one row list
     per community, wrapped in the shared reference-trust chrome. The component's
     inline styles now live as classes in resources/css/families/designators.css. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    // Lucide icons, copied path-for-path from the legacy render (lucide-react).
    $icon = static function (string $name, int $size, string $stroke = 'currentColor'): string {
        $paths = [
            'arrow-right' => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"'
            .' fill="none" stroke="'.$stroke.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            .' class="lucide lucide-'.$name.'" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
    };
@endphp

@section('content')
    <main class="dsg dsg-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">Officer Designators</span>
        </nav>

        @include('partials.trust.disclosure')

        <div class="dsg-eyebrow">// Officer Communities</div>
        <h1 class="dsg-h1">{{ $page->h1 ?? $page->title }}</h1>
        <p class="dsg-lede">{{ $intro }}</p>

        @include('partials.trust.byline')

        <section class="dsg-communities" aria-label="Officer communities">
            @foreach ($communities as $community)
                <a class="dsg-community" href="{{ $community['href'] }}">
                    <div class="dsg-community-count">{{ $community['designators']->count() }} Designator{{ $community['designators']->count() === 1 ? '' : 's' }}</div>
                    <div class="dsg-community-name">{{ $community['label'] }}</div>
                    <div class="dsg-community-tagline">{{ $community['tagline'] }}</div>
                    <div class="dsg-community-cta">Browse Community {!! $icon('arrow-right', 12) !!}</div>
                </a>
            @endforeach
        </section>

        @foreach ($communities as $community)
            <section class="dsg-group" aria-label="{{ $community['label'] }}">
                <h2 class="dsg-group-h2">{{ mb_strtoupper($community['label']) }}</h2>
                <ul class="dsg-rows">
                    @foreach ($community['designators'] as $designator)
                        <li class="dsg-row">
                            <a href="{{ PagePaths::child('designators', $designator->slug) }}">
                                <span class="dsg-row-code">{{ $designator->designator_code }}</span>
                                <span class="dsg-row-name">{{ $designator->name }} <span class="dsg-row-abbr">({{ $designator->abbreviation }})</span></span>
                                {!! $icon('arrow-right', 14, 'var(--gold)') !!}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        @include('partials.trust.editorial-policy')
    </main>
@endsection
