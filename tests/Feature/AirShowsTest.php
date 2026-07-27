<?php

declare(strict_types=1);

use App\Domain\Pillars\Enums\Admission;
use App\Domain\Pillars\Enums\AirShowStatus;
use App\Domain\Pillars\Models\AirShow;
use App\Domain\Pillars\Models\AirShowHubMeta;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;

it('casts the admission/status enums, flags and block JSON', function () {
    $show = AirShow::factory()->create();

    $fresh = $show->fresh();

    expect($fresh->admission)->toBe(Admission::Free)
        ->and($fresh->status)->toBe(AirShowStatus::Scheduled)
        ->and($fresh->published)->toBeTrue()
        ->and($fresh->date_unconfirmed)->toBeFalse()
        ->and($fresh->performers)->toBe(['Blue Angels', 'F-35 Demo Team'])
        ->and($fresh->sections[0]['blocks'][0]['kind'])->toBe('p')
        ->and($fresh->location['addressRegion'])->toBe('CA')
        ->and($fresh->offer['price'])->toBe('0');
});

it('emits Event schema only when published, dated and not a router page', function () {
    expect(AirShow::factory()->create()->emitsEventSchema())->toBeTrue()
        ->and(AirShow::factory()->unpublished()->create()->emitsEventSchema())->toBeFalse()
        ->and(AirShow::factory()->unconfirmed()->create()->emitsEventSchema())->toBeFalse()
        ->and(AirShow::factory()->router()->create()->emitsEventSchema())->toBeFalse();
});

it('attaches FAQs and sources via the shared polymorphic tables, in order', function () {
    $show = AirShow::factory()->create();
    Faq::factory()->for($show, 'faqable')->create(['question' => 'B', 'sort_order' => 2]);
    Faq::factory()->for($show, 'faqable')->create(['question' => 'A', 'sort_order' => 1]);
    Source::factory()->for($show, 'sourceable')->create();

    expect($show->faqs->pluck('question')->all())->toBe(['A', 'B'])
        ->and($show->sources)->toHaveCount(1)
        ->and($show->faqs->first()->faqable->is($show))->toBeTrue();
});

it('casts the hub meta JSON and attaches hub FAQs', function () {
    $hub = AirShowHubMeta::factory()->create();
    Faq::factory()->for($hub, 'faqable')->create(['question' => 'Q', 'sort_order' => 1]);

    $fresh = $hub->fresh();

    expect($fresh->base_path)->toBe('/air-show')
        ->and($fresh->intro)->toBe(['A lead paragraph for the hub.'])
        ->and($fresh->key_facts[0]['label'])->toBe('Shows')
        ->and($fresh->faqs->pluck('question')->all())->toBe(['Q']);
});

it('exposes admission and status display labels', function () {
    expect(Admission::Ticketed->label())->toBe('Ticketed')
        ->and(Admission::Free->label())->toBe('Free')
        ->and(AirShowStatus::Scheduled->label())->toBe('Scheduled')
        ->and(AirShowStatus::Postponed->label())->toBe('Postponed');
});
