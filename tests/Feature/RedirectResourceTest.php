<?php

declare(strict_types=1);

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Enums\RedirectMatchType;
use App\Domain\Publishing\Models\Page;
use App\Domain\Publishing\Models\Redirect;
use App\Filament\Resources\Pages\Pages\EditPage;
use App\Filament\Resources\Redirects\Pages\CreateRedirect;
use App\Filament\Resources\Redirects\Pages\ListRedirects;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function makeRedirect(array $attributes = []): Redirect
{
    static $n = 0;
    $n++;

    return Redirect::create(array_merge([
        'from_path' => "/old-{$n}/",
        'to_path' => "/new-{$n}/",
        'status' => 301,
        'reason' => 'manual',
        'match_type' => RedirectMatchType::Exact,
        'is_active' => true,
    ], $attributes));
}

it('lists redirects and filters by active state', function () {
    $active = makeRedirect();
    $inactive = makeRedirect(['is_active' => false]);

    Livewire::test(ListRedirects::class)
        ->assertCanSeeTableRecords([$active, $inactive])
        ->assertCanRenderTableColumn('from_path')
        ->assertCanRenderTableColumn('hits')
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('creates a redirect from the form', function () {
    Livewire::test(CreateRedirect::class)
        ->fillForm([
            'from_path' => '/legacy-page/',
            'to_path' => '/current-page/',
            'status' => 301,
            'match_type' => RedirectMatchType::Exact->value,
            'reason' => 'manual',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Redirect::query()->where('from_path', '/legacy-page/')->sole()->to_path)->toBe('/current-page/');
});

it('auto-creates a slug-change redirect when a page url_path is edited in the panel', function () {
    $page = Page::create([
        'page_type' => PageType::Static,
        'slug' => 'old',
        'url_path' => '/guides/old/',
        'title' => 'Guide',
        'is_published' => true,
        'noindex' => false,
    ]);

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['url_path' => '/guides/new/'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->fresh()?->url_path)->toBe('/guides/new/');

    $redirect = Redirect::query()->where('from_path', '/guides/old/')->sole();
    expect($redirect->to_path)->toBe('/guides/new/')
        ->and($redirect->reason)->toBe('slug-change')
        ->and($redirect->status)->toBe(301);
});
