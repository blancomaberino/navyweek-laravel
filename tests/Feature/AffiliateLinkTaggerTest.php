<?php

declare(strict_types=1);

use App\Domain\Catalog\Enums\Placement;
use App\Domain\Catalog\Models\AffiliateNetwork;
use App\Domain\Catalog\Services\AffiliateLinkTagger;

beforeEach(function () {
    $this->tagger = app(AffiliateLinkTagger::class);
});

it('appends the network sub-id param with the placement token', function () {
    $impact = AffiliateNetwork::factory()->create(['key' => 'impact', 'subid_param' => 'subId1', 'extra_params' => null]);

    expect($this->tagger->tag('https://example.com/p', Placement::HeroCta, $impact))
        ->toBe('https://example.com/p?subId1=nw-dsc-hero');
});

it('appends the extra params for the direct network', function () {
    $direct = AffiliateNetwork::factory()->direct()->create();

    $tagged = $this->tagger->tag('https://example.com/p', Placement::KeyfactsSource, $direct);

    expect($tagged)
        ->toContain('utm_source=navyweek')
        ->toContain('utm_medium=referral')
        ->toContain('utm_content=nw-dsc-keyfacts');
});

it('falls back to the direct network when none is given', function () {
    AffiliateNetwork::factory()->direct()->create();

    expect($this->tagger->tag('https://example.com/p', Placement::HeroCta))
        ->toBe('https://example.com/p?utm_source=navyweek&utm_medium=referral&utm_content=nw-dsc-hero');
});

it('preserves an existing query string and fragment', function () {
    $impact = AffiliateNetwork::factory()->create(['key' => 'impact', 'subid_param' => 'subId1', 'extra_params' => null]);

    expect($this->tagger->tag('https://example.com/p?a=1&b=2#section', Placement::HeroCta, $impact))
        ->toBe('https://example.com/p?a=1&b=2&subId1=nw-dsc-hero#section');
});

it('is idempotent — never overwrites a param already present', function () {
    $impact = AffiliateNetwork::factory()->create(['key' => 'impact', 'subid_param' => 'subId1', 'extra_params' => null]);
    $url = 'https://example.com/p?subId1=already-set';

    expect($this->tagger->tag($url, Placement::HeroCta, $impact))->toBe($url);
});

it('returns the input unchanged for empty or non-absolute URLs', function () {
    $impact = AffiliateNetwork::factory()->create(['key' => 'impact', 'subid_param' => 'subId1', 'extra_params' => null]);

    expect($this->tagger->tag('', Placement::HeroCta, $impact))->toBe('')
        ->and($this->tagger->tag('not a url', Placement::HeroCta, $impact))->toBe('not a url')
        ->and($this->tagger->tag('/relative/path', Placement::HeroCta, $impact))->toBe('/relative/path');
});
