{{-- Shared list of designator cards used by the hub and each community hub. --}}
<ul class="designator-card-list">
    @foreach ($designators as $designator)
        <li class="designator-card">
            <a href="{{ \App\Domain\Publishing\Support\PagePaths::child('designators', $designator->slug) }}">
                <span class="designator-card-code">{{ $designator->designator_code }}</span>
                <span class="designator-card-name">{{ $designator->name }}</span>
                @if ($designator->hero_tagline)
                    <span class="designator-card-tagline">{{ $designator->hero_tagline }}</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
