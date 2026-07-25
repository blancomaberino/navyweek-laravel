<?php

declare(strict_types=1);

use App\Domain\Shared\ValueObjects\UrlPath;

it('adds a leading and trailing slash', function () {
    expect((string) UrlPath::from('discount/nike'))->toBe('/discount/nike/');
});

it('preserves an already-canonical path', function () {
    expect((string) UrlPath::from('/discount/nike/'))->toBe('/discount/nike/');
});

it('lowercases the path', function () {
    expect((string) UrlPath::from('/Discount/Nike/'))->toBe('/discount/nike/');
});

it('collapses duplicate slashes', function () {
    expect((string) UrlPath::from('//discount///nike//'))->toBe('/discount/nike/');
});

it('strips scheme and host from a full url', function () {
    expect((string) UrlPath::from('https://www.navyweek.org/discount/nike/'))
        ->toBe('/discount/nike/');
});

it('normalizes every representation of root to "/"', function () {
    expect(UrlPath::root()->value())->toBe('/')
        ->and(UrlPath::from('/')->value())->toBe('/')
        ->and(UrlPath::from('///')->value())->toBe('/')
        ->and(UrlPath::from('https://www.navyweek.org')->value())->toBe('/');
});

it('reports root correctly', function () {
    expect(UrlPath::root()->isRoot())->toBeTrue()
        ->and(UrlPath::from('/discount/nike/')->isRoot())->toBeFalse();
});

it('compares by normalized value', function () {
    expect(UrlPath::from('discount/nike')->equals(UrlPath::from('/DISCOUNT/nike/')))
        ->toBeTrue();
});

it('rejects an empty path', function () {
    UrlPath::from('   ');
})->throws(InvalidArgumentException::class);
