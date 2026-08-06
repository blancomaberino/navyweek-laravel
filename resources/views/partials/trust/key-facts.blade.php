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
                @php
                    // Fact values are usually scalars but some imported records carry a
                    // list (e.g. several performers) — flatten rather than 500.
                    $factValue = $fact['value'] ?? '';
                    $factValue = is_array($factValue)
                        ? implode(', ', array_map(static fn ($v): string => is_scalar($v) ? (string) $v : '', $factValue))
                        : (string) $factValue;
                @endphp
                <div>
                    <dt>{{ $fact['label'] ?? '' }}</dt>
                    <dd>{{ $factValue }}</dd>
                </div>
            @endforeach
        </dl>
        @if ($source || $lastVerified)
            <p class="key-facts-source">
                @if ($source)
                    Source:
                    {{-- `key_facts` is editor-supplied, so the source URL goes through the
                         same scheme allowlist as editable nav links (LinkUrl) — a stored
                         `javascript:`/`data:` value must never become an executable href. --}}
                    <a href="{{ \App\Domain\Navigation\Support\LinkUrl::sanitize((string) ($source['url'] ?? '')) }}"
                       target="_blank"
                       rel="{{ $source['rel'] ?? 'noopener noreferrer' }}">{{ $source['label'] ?? '' }}</a>
                @endif
                @if ($source && $lastVerified) <span>&middot;</span> @endif
                @if ($lastVerified) Last verified: {{ $lastVerified }} @endif
            </p>
        @endif
    </section>
@endif
