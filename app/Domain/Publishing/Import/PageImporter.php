<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Import;

use App\Domain\Catalog\Models\Offer;
use App\Domain\Crm\Models\Connection;
use App\Domain\Publishing\Models\Page;
use App\Domain\Shared\Import\Row;
use App\Domain\Shared\Import\SeedArtifact;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Stage-B importer for the discount-brand `pages`. Each row points its polymorphic
 * `pageable` at the brand's primary Offer (resolved connection slug → connection
 * → primary offer). Idempotent upsert on the stable `generation_key`
 * ("discount-brand:{connection_slug}"), one transaction. Offers must already be imported.
 *
 * Identity vs. location: keying on generation_key (not url_path) lets an editor rename a
 * discount page in the admin panel (url_path_is_custom) and keep that path across a
 * re-import — the CSV url_path is only (re)applied to pages the editor hasn't pinned.
 * As a migration seed this does not emit redirects (that is ChangeUrlPathAction's job).
 */
final class PageImporter
{
    /**
     * @return array<string, int> row counts by table
     */
    public function import(): array
    {
        return DB::transaction(function (): array {
            /** @var array<string, int> $connectionIdBySlug */
            $connectionIdBySlug = Connection::query()->pluck('id', 'slug')->all();
            /** @var array<int, int> $primaryOfferByConnectionId */
            $primaryOfferByConnectionId = Offer::query()
                ->where('is_primary', true)
                ->pluck('id', 'connection_id')
                ->all();

            // The default byline (author + reviewer), resolved from the editorial
            // users EditorialTeamSeeder creates. A page keeps any assignment it
            // already has (an admin override); only unassigned pages get the default.
            $defaultAuthorId = self::userIdBySlug(Config::string('site.editorial.default_author_slug'));
            $defaultReviewerId = self::userIdBySlug(Config::string('site.editorial.default_reviewer_slug'));

            $rows = SeedArtifact::read('pages');

            foreach ($rows as $row) {
                $slug = Row::str($row, 'connection_slug');
                unset($row['connection_slug']);

                $connectionId = $connectionIdBySlug[$slug] ?? null;
                $offerId = $connectionId !== null ? ($primaryOfferByConnectionId[$connectionId] ?? null) : null;

                // Point the polymorphic `pageable` at the brand's primary offer (or
                // clear it) and upsert the page in a single write, keyed on the stable
                // generation_key so an editor-renamed page is found again (not duplicated).
                $page = Page::query()->firstOrNew(['generation_key' => "discount-brand:{$slug}"]);
                // Adopt a pre-generation_key row (keyless) at this path instead of
                // inserting a duplicate that collides on the unique url_path.
                if (! $page->exists) {
                    $legacy = Page::query()
                        ->whereNull('generation_key')
                        ->where('url_path', $row['url_path'])
                        ->first();
                    if ($legacy !== null) {
                        $page = $legacy;
                        $page->generation_key = "discount-brand:{$slug}";
                    }
                }
                // An editor rename pins the path: keep it instead of snapping back to the
                // CSV default. A page the editor hasn't renamed tracks the CSV url_path.
                if ($page->exists && $page->url_path_is_custom) {
                    unset($row['url_path']);
                }
                $page->fill($row);
                if ($page->author_id === null && $defaultAuthorId !== null) {
                    $page->author_id = $defaultAuthorId;
                }
                if ($page->reviewer_id === null && $defaultReviewerId !== null) {
                    $page->reviewer_id = $defaultReviewerId;
                }
                if ($offerId !== null) {
                    $offer = new Offer;
                    $offer->id = $offerId;
                    $page->pageable()->associate($offer);
                } else {
                    $page->pageable()->dissociate();
                }
                $page->save();
            }

            return ['pages' => count($rows)];
        });
    }

    /**
     * The id of the user with this profile slug, or null when no such user exists
     * yet (the byline seeder hasn't run) — in which case the byline is left empty.
     */
    private static function userIdBySlug(string $slug): ?int
    {
        $id = User::query()->where('slug', $slug)->value('id');

        return is_int($id) ? $id : null;
    }
}
