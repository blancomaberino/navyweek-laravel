<?php

declare(strict_types=1);

use App\Domain\Publishing\Models\Page;
use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Filament\Resources\Authors\Pages\ListAuthors;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    // The acting admin has no editorial `slug`, so it must NOT appear in the
    // slug-scoped Authors list — a live check that the scope excludes ops accounts.
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lists only editorial byline profiles (users with a slug)', function () {
    $author = User::factory()->create(['slug' => 't-alford', 'name' => 'T Alford']);
    $reviewer = User::factory()->create(['slug' => 'erik-rivera', 'name' => 'Erik Rivera']);

    Livewire::test(ListAuthors::class)
        ->assertCanSeeTableRecords([$author, $reviewer])
        // The ops admin (no slug) is out of scope.
        ->assertCanNotSeeTableRecords([$this->admin])
        ->assertCanRenderTableColumn('authored_pages_count')
        ->assertCanRenderTableColumn('is_admin');
});

it('creates a byline-only author with an unusable password and no panel access', function () {
    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'T Madden Alford',
            'slug' => 't-alford',
            'email' => 'madden.alford@navyweek.org',
            'job_title' => 'Editor, NavyWeek.org',
            'avatar_path' => '/authors/t-alford.jpg',
            'linkedin_url' => 'https://www.linkedin.com/in/t-alford',
            'credentials' => "U.S. Naval Academy '02",
            'bio' => 'A long-form biography paragraph for the profile page.',
            'knows_about' => ['military discounts', 'veteran benefits'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $author = User::query()->where('slug', 't-alford')->sole();

    expect($author->name)->toBe('T Madden Alford')
        ->and($author->job_title)->toBe('Editor, NavyWeek.org')
        ->and($author->linkedin_url)->toBe('https://www.linkedin.com/in/t-alford')
        ->and($author->bio)->toBe('A long-form biography paragraph for the profile page.')
        ->and($author->knows_about)->toBe(['military discounts', 'veteran benefits'])
        ->and($author->is_admin)->toBeFalse()
        // A random, unusable password satisfies the NOT NULL column and is hashed.
        ->and($author->password)->toStartWith('$');
});

it('persists the guarded is_admin flag when granting panel access on create', function () {
    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'Erik Rivera',
            'slug' => 'erik-rivera',
            'email' => 'erik.rivera@navyweek.org',
            'is_admin' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::query()->where('slug', 'erik-rivera')->sole()->is_admin)->toBeTrue();
});

it('toggles the guarded is_admin flag from the edit form', function () {
    $author = User::factory()->create(['slug' => 't-alford', 'is_admin' => false]);

    Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
        ->fillForm(['is_admin' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($author->refresh()->is_admin)->toBeTrue();
});

it('rejects a duplicate author slug', function () {
    User::factory()->create(['slug' => 't-alford']);

    Livewire::test(CreateAuthor::class)
        ->fillForm(['name' => 'Dupe', 'slug' => 't-alford', 'email' => 'dupe@navyweek.org'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('rejects a non-canonical slug', function () {
    Livewire::test(CreateAuthor::class)
        ->fillForm(['name' => 'Bad Slug', 'slug' => 'T Alford', 'email' => 'bad@navyweek.org'])
        ->call('create')
        ->assertHasFormErrors(['slug']);
});

it('requires the avatar path to be site-relative', function () {
    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'Bad Avatar',
            'slug' => 'bad-avatar',
            'email' => 'avatar@navyweek.org',
            'avatar_path' => 'https://cdn.example.com/x.jpg',
        ])
        ->call('create')
        ->assertHasFormErrors(['avatar_path']);
});

it('rejects a protocol-relative avatar path', function () {
    // `//host/x.jpg` starts with a slash but would resolve to an external host as
    // an <img src> — the regex must reject the double leading slash.
    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'Sneaky Avatar',
            'slug' => 'sneaky-avatar',
            'email' => 'sneaky@navyweek.org',
            'avatar_path' => '//cdn.evil.com/x.jpg',
        ])
        ->call('create')
        ->assertHasFormErrors(['avatar_path']);
});

it('rejects a non-URL linkedin value', function () {
    Livewire::test(CreateAuthor::class)
        ->fillForm([
            'name' => 'Bad Link',
            'slug' => 'bad-link',
            'email' => 'link@navyweek.org',
            'linkedin_url' => 'not-a-url',
        ])
        ->call('create')
        ->assertHasFormErrors(['linkedin_url']);
});

it('keeps the byline when an author is deleted (pages survive, byline clears)', function () {
    $author = User::factory()->create(['slug' => 't-alford']);
    $page = Page::factory()->create(['author_id' => $author->id]);

    Livewire::test(EditAuthor::class, ['record' => $author->getRouteKey()])
        ->callAction('delete');

    expect(User::query()->whereKey($author->id)->exists())->toBeFalse()
        ->and($page->refresh()->author_id)->toBeNull();
});
