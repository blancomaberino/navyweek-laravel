<?php

declare(strict_types=1);

use App\Domain\Research\Models\Skill;
use App\Filament\Resources\Skills\Pages\CreateSkill;
use App\Filament\Resources\Skills\Pages\EditSkill;
use App\Filament\Resources\Skills\Pages\ListSkills;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('lists skills with the version badge and brief-count column', function () {
    $a = Skill::create(['key' => 'military-discount-research', 'name' => 'Military Discount Research', 'current_version' => '1.4.0']);
    $b = Skill::create(['key' => 'seo-geo', 'name' => 'SEO / GEO', 'current_version' => '2.0.0']);

    Livewire::test(ListSkills::class)
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanRenderTableColumn('current_version')
        ->assertCanRenderTableColumn('research_count');
});

it('creates a skill from the form', function () {
    Livewire::test(CreateSkill::class)
        ->fillForm([
            'key' => 'brand-archetypes',
            'name' => 'Brand Archetypes',
            'current_version' => '1.0.0',
            'source_ref' => '.claude/skills/brand-archetypes/SKILL.md',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Skill::query()->where('key', 'brand-archetypes')->sole()->name)->toBe('Brand Archetypes');
});

it('does not persist the read-only content_hash from the form', function () {
    $skill = Skill::create([
        'key' => 'seo-geo',
        'name' => 'SEO / GEO',
        'current_version' => '2.0.0',
        'content_hash' => 'abc123',
    ]);

    Livewire::test(EditSkill::class, ['record' => $skill->getRouteKey()])
        ->fillForm(['name' => 'SEO and GEO'])
        ->call('save')
        ->assertHasNoFormErrors();

    $skill->refresh();
    // Name updates; the automation-maintained hash is untouched by the form.
    expect($skill->name)->toBe('SEO and GEO')
        ->and($skill->content_hash)->toBe('abc123');
});

it('rejects a duplicate skill key', function () {
    Skill::create(['key' => 'seo-geo', 'name' => 'SEO / GEO', 'current_version' => '1.0.0']);

    Livewire::test(CreateSkill::class)
        ->fillForm(['key' => 'seo-geo', 'name' => 'Dupe', 'current_version' => '1.0.0'])
        ->call('create')
        ->assertHasFormErrors(['key']);
});
