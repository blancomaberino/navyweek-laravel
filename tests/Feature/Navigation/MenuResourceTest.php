<?php

declare(strict_types=1);

use App\Domain\Navigation\Enums\MenuLocation;
use App\Domain\Navigation\Models\Menu;
use App\Domain\Navigation\Models\MenuItem;
use App\Filament\Resources\Menus\Pages\CreateMenu;
use App\Filament\Resources\Menus\Pages\EditMenu;
use App\Filament\Resources\Menus\Pages\ListMenus;
use App\Filament\Resources\Menus\RelationManagers\MenuItemsRelationManager;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lists menus with the links count and location badge', function () {
    $header = Menu::factory()->location(MenuLocation::Header)->create(['key' => 'header-primary']);
    MenuItem::factory()->for($header)->count(3)->create();
    $footer = Menu::factory()->location(MenuLocation::Footer)->create(['key' => 'footer-a']);

    Livewire::test(ListMenus::class)
        ->assertCanSeeTableRecords([$header, $footer])
        ->assertCanRenderTableColumn('items_count')
        ->assertCanRenderTableColumn('location');
});

it('filters menus by location', function () {
    $header = Menu::factory()->location(MenuLocation::Header)->create(['key' => 'h']);
    $legal = Menu::factory()->location(MenuLocation::Legal)->create(['key' => 'l']);

    Livewire::test(ListMenus::class)
        ->filterTable('location', MenuLocation::Footer->value)
        ->assertCanNotSeeTableRecords([$header, $legal]);
});

it('creates a menu, normalizing the key to kebab-case', function () {
    Livewire::test(CreateMenu::class)
        ->fillForm([
            'key' => 'Footer Extra Links',
            'name' => 'Extra Links',
            'location' => MenuLocation::Footer->value,
            'sort_order' => 5,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Menu::query()->where('key', 'footer-extra-links')->exists())->toBeTrue();
});

it('rejects a second menu in the singular header region', function () {
    Menu::factory()->location(MenuLocation::Header)->create(['key' => 'header-primary']);

    Livewire::test(CreateMenu::class)
        ->fillForm([
            'key' => 'header-two',
            'name' => 'Second header',
            'location' => MenuLocation::Header->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['location']);

    expect(Menu::query()->where('location', MenuLocation::Header)->count())->toBe(1);
});

it('allows multiple footer menus (columns)', function () {
    Menu::factory()->location(MenuLocation::Footer)->create(['key' => 'footer-a']);

    Livewire::test(CreateMenu::class)
        ->fillForm([
            'key' => 'footer-b',
            'name' => 'Second column',
            'location' => MenuLocation::Footer->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Menu::query()->where('location', MenuLocation::Footer)->count())->toBe(2);
});

it('rejects a duplicate menu key', function () {
    Menu::factory()->create(['key' => 'footer-a']);

    Livewire::test(CreateMenu::class)
        ->fillForm([
            'key' => 'footer-a',
            'name' => 'Dup',
            'location' => MenuLocation::Footer->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['key']);
});

it('adds a link to a menu through the relation manager', function () {
    $menu = Menu::factory()->location(MenuLocation::Header)->create(['key' => 'header-primary']);

    Livewire::test(MenuItemsRelationManager::class, [
        'ownerRecord' => $menu,
        'pageClass' => EditMenu::class,
    ])
        ->callTableAction('create', data: [
            'label' => 'Contact',
            'url' => '/contact/',
            'is_active' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect($menu->items()->where('label', 'Contact')->where('url', '/contact/')->exists())->toBeTrue();
});

it('rejects a menu-item url with a disallowed scheme', function () {
    $menu = Menu::factory()->location(MenuLocation::Header)->create(['key' => 'header-primary']);

    Livewire::test(MenuItemsRelationManager::class, [
        'ownerRecord' => $menu,
        'pageClass' => EditMenu::class,
    ])
        ->callTableAction('create', data: [
            'label' => 'Evil',
            'url' => 'javascript:alert(1)',
            'is_active' => true,
        ])
        ->assertHasTableActionErrors(['url']);

    expect($menu->items()->where('label', 'Evil')->exists())->toBeFalse();
});

it('nests a link under a parent through the relation manager', function () {
    $menu = Menu::factory()->location(MenuLocation::Header)->create(['key' => 'header-primary']);
    $parent = MenuItem::factory()->for($menu)->create(['label' => 'Reference', 'url' => '/navy-reference/']);

    Livewire::test(MenuItemsRelationManager::class, [
        'ownerRecord' => $menu,
        'pageClass' => EditMenu::class,
    ])
        ->callTableAction('create', data: [
            'label' => 'Bases',
            'url' => '/navy-bases/',
            'parent_id' => $parent->id,
            'is_active' => true,
        ])
        ->assertHasNoTableActionErrors();

    expect($menu->items()->where('label', 'Bases')->value('parent_id'))->toBe($parent->id);
});
