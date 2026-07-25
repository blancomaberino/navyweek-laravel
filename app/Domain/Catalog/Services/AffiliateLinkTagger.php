<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Enums\Placement;
use App\Domain\Catalog\Models\AffiliateNetwork;
use App\Domain\Catalog\Repositories\AffiliateNetworkRepositoryInterface;

/**
 * PHP port of `withPlacement` (legacy `links.ts`) — the single choke point every
 * outbound offer link goes through. Appends the network-correct placement sub-ID
 * (and any extra params) to the URL.
 *
 * Contract:
 *  - Preserves the existing query string and fragment exactly (only appends — it
 *    never re-encodes or reorders what is already there). This is stricter than
 *    the JS original, which round-trips through `new URL().toString()` and thus
 *    re-encodes; for the clean official URLs we tag the two agree, and preserving
 *    bytes is the safer choice.
 *  - Idempotent: never overwrites a param already present, so tagging twice is a
 *    no-op.
 *  - Returns the input unchanged when the URL is empty or not an absolute URL.
 */
final class AffiliateLinkTagger
{
    /** Memoized `direct` fallback network — resolved once per tagger instance. */
    private ?AffiliateNetwork $directNetwork = null;

    public function __construct(
        private readonly AffiliateNetworkRepositoryInterface $networks,
    ) {}

    /**
     * @param  AffiliateNetwork|null  $network  null falls back to the `direct` network (UTM tagging).
     */
    public function tag(string $url, Placement $placement, ?AffiliateNetwork $network = null): string
    {
        if ($url === '') {
            return $url;
        }

        // Absolute-URL guard — the JS `new URL(url)` throws (→ return input) on a
        // relative or unparseable URL.
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return $url;
        }

        $network ??= $this->directNetwork ??= $this->networks->findByKey('direct');
        if ($network === null) {
            return $url;
        }

        // Split off the fragment and query so the existing parts stay byte-for-byte.
        [$beforeFragment, $fragment] = array_pad(explode('#', $url, 2), 2, '');
        [$path, $query] = array_pad(explode('?', $beforeFragment, 2), 2, '');

        parse_str($query, $existing);

        $additions = [];
        foreach (($network->extra_params ?? []) as $key => $value) {
            if (! array_key_exists($key, $existing)) {
                $additions[$key] = $value;
            }
        }
        if (! array_key_exists($network->subid_param, $existing)) {
            $additions[$network->subid_param] = $placement->token();
        }

        foreach ($additions as $key => $value) {
            $pair = rawurlencode($key).'='.rawurlencode($value);
            $query = $query === '' ? $pair : $query.'&'.$pair;
        }

        return $path
            .($query === '' ? '' : '?'.$query)
            .($fragment === '' ? '' : '#'.$fragment);
    }
}
