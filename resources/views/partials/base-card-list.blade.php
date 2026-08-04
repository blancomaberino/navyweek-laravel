{{-- Shared list of base cards used by every bases hub. --}}
<ul class="base-card-list">
    @foreach ($bases as $base)
        <li class="base-card">
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('bases', $base->slug) }}">
                <span class="base-card-name">{{ $base->name }}</span>
                @if ($base->city)
                    <span class="base-card-loc">{{ $base->city }}@if ($base->state_name), {{ $base->state_name }}@elseif ($base->country), {{ $base->country }}@endif</span>
                @endif
                @if ($base->hero_tagline)
                    <span class="base-card-tagline">{{ $base->hero_tagline }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
