<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\AffiliateLink;
use App\Domain\Catalog\Models\Offer;
use App\Domain\Catalog\Models\OfferTier;
use App\Domain\Catalog\Models\RedemptionStep;
use App\Domain\Crm\Import\ConnectionImporter;
use App\Domain\Crm\Models\Connection;
use App\Domain\Crm\Models\ConnectionAlias;
use App\Domain\Publishing\Models\Page;
use App\Domain\Research\Import\ResearchImporter;
use App\Domain\Research\Models\Research;
use App\Domain\Shared\Import\SeedArtifact;
use App\Domain\Shared\Models\Faq;
use App\Domain\Shared\Models\Source;
use Database\Seeders\EditorialTeamSeeder;

/**
 * Importing the whole ~15.3k-brand universe is expensive, so the suite runs the
 * full core once (counts + normalization + the two self-referential resolutions),
 * once more for idempotency, and a lighter connections-only pass for the briefs.
 *
 * @param  'connections'|'connection-aliases'|'offers'|'pages'|'research'  $name
 * @return array<int, array<string, mixed>>
 */
function coreArtifact(string $name): array
{
    /** @var array<string, array<int, array<string, mixed>>> $cache */
    static $cache = [];

    return $cache[$name] ??= SeedArtifact::read($name);
}

/** Sum a nested child list across every offer row in the artifact. */
function offerChildTotal(string $key): int
{
    $total = 0;
    foreach (coreArtifact('offers') as $offer) {
        $total += match ($key) {
            'steps' => count($offer['online_steps']) + count($offer['in_store_steps']),
            'affiliate_links' => count($offer['affiliate_links']),
            default => count($offer[$key]),
        };
    }

    return $total;
}

it('imports and normalizes the full discount core from the committed artifacts', function () {
    // The editorial byline exists first, so the importer can assign the default
    // author + reviewer to every page it creates.
    $this->seed(EditorialTeamSeeder::class);

    $this->artisan('import:discount-core')->assertSuccessful();

    // Every table lands the artifact's row counts.
    expect(Connection::count())->toBe(count(coreArtifact('connections')))->toBeGreaterThan(15000)
        ->and(Offer::count())->toBe(count(coreArtifact('offers')))
        ->and(OfferTier::count())->toBe(offerChildTotal('tiers'))
        ->and(RedemptionStep::count())->toBe(offerChildTotal('steps'))
        ->and(AffiliateLink::count())->toBe(offerChildTotal('affiliate_links'))
        ->and(Page::count())->toBe(count(coreArtifact('pages')))
        ->and(Research::count())->toBe(count(coreArtifact('research')))
        // the 981 published brands are the editorial overlay onto the queue universe
        ->and(Connection::query()->where('status', 'published')->count())->toBe(count(coreArtifact('offers')));

    // YETI, normalized connection → offer (+children) → page.
    $yeti = Connection::query()->where('slug', 'yeti')->sole();
    expect($yeti->status->value)->toBe('published')
        ->and($yeti->official_url)->toBe('https://www.yeti.com/id-me-deals');

    $offer = $yeti->offers()->where('is_primary', true)->sole();
    expect($offer->offer_type->value)->toBe('everyday')
        ->and($offer->tiers()->count())->toBe(6)
        ->and($offer->redemptionSteps()->count())->toBe(7) // 4 online + 3 in-store
        ->and($offer->faqs()->count())->toBe(8)
        ->and($offer->sources()->count())->toBe(2)
        // the 9-boolean legacy audience collapsed to the 5 distinct enum cases
        ->and($offer->audiences->pluck('key')->map->value->sort()->values()->all())
        ->toBe(['government', 'healthcare', 'military', 'teacher', 'veteran'])
        // both monetized placements, network resolved to a seeded row
        ->and($offer->affiliateLinks()->count())->toBe(2)
        ->and($offer->affiliateLinks()->whereNotNull('affiliate_network_id')->count())->toBe(2);

    $page = Page::query()->where('url_path', '/discount/yeti-military-veteran/')->sole();
    expect($page->pageable_id)->toBe($offer->id)
        ->and($page->pageable_type)->toBe($offer->getMorphClass())
        // the default byline was assigned from the seeded editorial users
        ->and($page->author?->slug)->toBe('t-alford')
        ->and($page->reviewer?->slug)->toBe('erik-rivera');

    // The two self-referential slug resolutions: a duplicate brand → its canonical,
    // and an alias → its canonical connection.
    $apple = Connection::query()->where('slug', 'apple')->sole();
    expect(Connection::query()->where('slug', 'discount-apple')->sole()->duplicate_of)->toBe($apple->id);

    $alias = ConnectionAlias::query()->where('alias_slug', '1800-flowers')->sole();
    expect($alias->connection_id)->toBe(Connection::query()->where('slug', '1800flowers')->sole()->id);
});

it('populates raw_markdown verbatim from the in-repo brief (zero data loss)', function () {
    app(ConnectionImporter::class)->import();
    $research = app(ResearchImporter::class)->import();

    // Every research row resolves to a connection and its brief file exists on disk,
    // so raw_markdown must be fully populated — a null would be silent data loss.
    expect($research['research'])->toBe(count(coreArtifact('research')))
        ->and($research['research_with_markdown'])->toBe($research['research']);

    $yeti = Connection::query()->where('slug', 'yeti')->sole();
    $brief = Research::query()->where('connection_id', $yeti->id)->sole();
    expect($brief->raw_markdown)->toBeString()->not->toBeEmpty()
        ->and($brief->brief_path)->toBe('research/discounts/yeti.md')
        ->and($brief->version)->toBe(1);
});

it('is idempotent — re-running replaces children without duplicates or orphans', function () {
    $this->artisan('import:discount-core')->assertSuccessful();

    $faqs = Faq::count();
    $sources = Source::count();

    $this->artisan('import:discount-core')->assertSuccessful();

    expect(Connection::count())->toBe(count(coreArtifact('connections')))
        ->and(Offer::count())->toBe(count(coreArtifact('offers')))
        ->and(OfferTier::count())->toBe(offerChildTotal('tiers'))
        ->and(RedemptionStep::count())->toBe(offerChildTotal('steps'))
        ->and(AffiliateLink::count())->toBe(offerChildTotal('affiliate_links'))
        ->and(Page::count())->toBe(count(coreArtifact('pages')))
        // wholesale-replaced children must not accumulate across runs
        ->and(Faq::count())->toBe($faqs)
        ->and(Source::count())->toBe($sources);
});
