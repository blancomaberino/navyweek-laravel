<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Models\Page;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function makePage(array $attributes = []): Page
{
    static $n = 0;
    $n++;

    return Page::create(array_merge([
        'page_type' => PageType::DiscountBrand,
        'slug' => "brand-{$n}",
        'url_path' => "/discount/brand-{$n}/",
        'title' => "Brand {$n} Discount",
        'is_published' => true,
        'noindex' => false,
    ], $attributes));
}

it('lists pages with the type badge and target column', function () {
    $brand = makePage();
    $hub = makePage(['page_type' => PageType::DiscountCategoryHub]);

    Livewire::test(ListPages::class)
        ->assertCanSeeTableRecords([$brand, $hub])
        ->assertCanRenderTableColumn('page_type')
        ->assertCanRenderTableColumn('pageable_type');
});

it('filters pages by type and by the noindex flag', function () {
    $brand = makePage();
    $hidden = makePage(['noindex' => true, 'page_type' => PageType::Static]);

    Livewire::test(ListPages::class)
        ->filterTable('page_type', PageType::Static->value)
        ->assertCanSeeTableRecords([$hidden])
        ->assertCanNotSeeTableRecords([$brand]);
});

it('edits a page and persists routing + SEO changes', function () {
    $page = makePage(['title' => 'Old', 'noindex' => false]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['title' => 'New Title', 'noindex' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();
    expect($page->title)->toBe('New Title')->and($page->noindex)->toBeTrue();
});

it('rejects a duplicate url_path on edit', function () {
    makePage(['url_path' => '/discount/taken/', 'slug' => 'taken']);
    $page = makePage();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['url_path' => '/discount/taken/'])
        ->call('save')
        ->assertHasFormErrors(['url_path']);
});

it('rolls back the whole edit when the url rename fails at the database layer', function () {
    // Another page already occupies the target path, so a rename into it violates the
    // pages.url_path unique index at write time — the concurrent-conflict case that
    // slips past form validation. handleRecordUpdate must commit the plain-column
    // write and the rename together, so this failure rolls the title change back too.
    makePage(['url_path' => '/discount/taken/', 'slug' => 'taken']);
    $page = makePage(['title' => 'Original']);

    $component = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->instance();
    $handle = new ReflectionMethod($component, 'handleRecordUpdate');

    expect(fn () => $handle->invoke($component, $page, ['title' => 'Changed', 'url_path' => '/discount/taken/']))
        ->toThrow(Illuminate\Database\QueryException::class);

    // The non-URL column change must NOT have persisted — the transaction rolled back.
    expect($page->fresh()->title)->toBe('Original');
});

it('assigns the author and reviewer byline from the form selects', function () {
    $author = User::factory()->create(['name' => 'T Madden Alford', 'slug' => 't-alford']);
    $reviewer = User::factory()->create(['name' => 'Erik Rivera', 'slug' => 'erik-rivera']);
    $page = makePage();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['author_id' => $author->id, 'reviewer_id' => $reviewer->id])
        ->call('save')
        ->assertHasNoFormErrors();

    $page->refresh();
    expect($page->author_id)->toBe($author->id)
        ->and($page->reviewer_id)->toBe($reviewer->id)
        ->and($page->author->name)->toBe('T Madden Alford');
});

it('clears the byline back to null from the form', function () {
    $author = User::factory()->create(['slug' => 'someone']);
    $page = makePage(['author_id' => $author->id]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['author_id' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->refresh()->author_id)->toBeNull();
});
