{{-- KeyFacts block — ported 1:1 from the legacy src/components/KeyFacts.tsx.
     A real <dl> with a visible <h2>, an inline source link and an optional
     "Last verified" stamp, so LLM crawlers can cite it. Driven entirely by the
     page's `key_facts` CMS column:
     {title, facts: [{label, value}], source: {label, url, rel}, lastVerified}. --}}
@php
    $kf = $keyFacts ?? $page->key_facts ?? null;
    $facts = $kf['facts'] ?? [];
    $kfTitle = $kf['title'] ?? 'Key Facts';
    $source = $kf['source'] ?? null;
    $lastVerified = $kf['lastVerified'] ?? null;
@endphp
@if (! empty($facts))
    <section class="key-facts" aria-label="{{ $kf['ariaLabel'] ?? $kfTitle }}" data-llm-key-facts="1">
        <h2>{{ $kfTitle }}</h2>
        <dl>
            @foreach ($facts as $fact)
                <div>
                    <dt>{{ $fact['label'] ?? '' }}</dt>
                    <dd>{{ $fact['value'] ?? '' }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($source || $lastVerified)
            <p class="key-facts-source">
                @if ($source)
                    Source:
                    <a href="{{ $source['url'] }}" target="_blank" rel="{{ $source['rel'] ?? 'noopener noreferrer' }}">{{ $source['label'] }}</a>
                @endif
                @if ($source && $lastVerified) <span>&middot;</span> @endif
                @if ($lastVerified) Last verified: {{ $lastVerified }} @endif
            </p>
        @endif
    </section>
@endif
