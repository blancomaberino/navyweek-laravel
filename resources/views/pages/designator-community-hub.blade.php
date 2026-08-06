@extends('layouts.base')

{{-- /navy-designators/{community}/ — one officer community's designators. A 1:1
     port of the legacy src/page-views/NavyDesignatorsCommunity.tsx; the
     component's inline styles live in resources/css/families/designators.css. --}}
@php
    use App\Domain\Publishing\Support\PagePaths;

    $designatorRoot = PagePaths::root('designators');

    // Lucide icons, copied path-for-path from the legacy render (lucide-react).
    $icon = static function (string $name, int $size, string $stroke = 'currentColor'): string {
        $paths = [
            'arrow-right' => '<path d="M5 12h14"></path><path d="m12 5 7 7-7 7"></path>',
            'chevron-left' => '<path d="m15 18-6-6 6-6"></path>',
        ];

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"'
            .' fill="none" stroke="'.$stroke.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            .' class="lucide lucide-'.$name.'" aria-hidden="true">'.($paths[$name] ?? '').'</svg>';
    };
@endphp

@section('content')
    <main class="dsg dsg-community-hub">
        @include('partials.trust.back-link')

        <nav class="breadcrumb" aria-label="Breadcrumb">
            <a href="/">Home</a>
            <span aria-hidden="true">/</span>
            <a href="{{ $designatorRoot }}">Officer Designators</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $communityLabel }}</span>
        </nav>

        <div class="dsg-eyebrow">// {{ $designators->count() }} Designators</div>
        <h1 class="dsg-h1">{{ $page->h1 ?? $page->title }}</h1>
        <p class="dsg-lede">{{ $intro }}</p>

        <ul class="dsg-cards">
            @foreach ($designators as $designator)
                <li>
                    <a href="{{ PagePaths::child('designators', $designator->slug) }}">
                        <div class="dsg-card-head">
                            <span class="dsg-card-code">{{ $designator->designator_code }} &middot; {{ $designator->abbreviation }}</span>
                            {!! $icon('arrow-right', 14, 'var(--gold)') !!}
                        </div>
                        <div class="dsg-card-name">{{ $designator->name }}</div>
                        <div class="dsg-card-tagline">{{ $designator->hero_tagline }}</div>
                    </a>
                </li>
            @endforeach
        </ul>

        <div class="dsg-community-foot">
            <a href="{{ $designatorRoot }}">{!! $icon('chevron-left', 14) !!} All Officer Designators</a>
        </div>
    </main>
@endsection
