<?php

declare(strict_types=1);

use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Repositories\EloquentPageRepository;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;

it('binds the page repository to its Eloquent implementation', function () {
    expect(app(PageRepositoryInterface::class))
        ->toBeInstanceOf(EloquentPageRepository::class);
});

it('reports whether a published path exists', function () {
    Page::factory()->create(['url_path' => '/discount/nike/']);
    Page::factory()->unpublished()->create(['url_path' => '/hidden/']);
    $repository = app(PageRepositoryInterface::class);

    expect($repository->publishedPathExists('/discount/nike/'))->toBeTrue()
        ->and($repository->publishedPathExists('/hidden/'))->toBeFalse()
        ->and($repository->publishedPathExists('/missing/'))->toBeFalse();
});

it('finds a published page by path with its pageable eager-loaded', function () {
    $connection = Connection::factory()->create();
    Page::factory()->create([
        'page_type' => PageType::DiscountBrand,
        'url_path' => '/discount/nike/',
        'pageable_type' => $connection->getMorphClass(),
        'pageable_id' => $connection->id,
    ]);

    $page = app(PageRepositoryInterface::class)->findPublishedByPath('/discount/nike/');

    expect($page)->not->toBeNull()
        ->and($page->relationLoaded('pageable'))->toBeTrue()
        ->and($page->pageable->is($connection))->toBeTrue();
});

it('does not find an unpublished or unknown path', function () {
    Page::factory()->unpublished()->create(['url_path' => '/hidden/']);
    $repository = app(PageRepositoryInterface::class);

    expect($repository->findPublishedByPath('/hidden/'))->toBeNull()
        ->and($repository->findPublishedByPath('/missing/'))->toBeNull();
});
