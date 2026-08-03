<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\AuthorRepositoryInterface;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use App\Domain\Publishing\Support\PagePaths;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Generates the `/authors/{slug}/` profile page for every editorial `users` row that
 * has a public profile slug — one page per byline (T Madden Alford, Erik Rivera, …).
 * The page presents the User (its `pageable`) directly, so the profile is data-driven
 * from the account, not a CMS `body_blocks` body (the hub pattern, like the home page).
 *
 * Idempotent: keyed on the stable `author:{slug}` `generation_key`, so the build clock
 * preserves `date_published`, an editor rename is preserved, and a `config('publishing
 * .paths.authors')` prefix change moves every profile (auto-301). The url_path is built
 * via `PagePaths` (never a hardcoded `/authors/` literal), keeping the family single-knob.
 */
final class GenerateAuthorPagesAction
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
        private readonly AuthorRepositoryInterface $authors,
    ) {}

    public function __invoke(): int
    {
        $now = Carbon::now();
        $count = 0;

        foreach ($this->authors->publicProfiles() as $author) {
            $slug = (string) $author->slug;

            $this->pages->upsertPillarPage(
                "author:{$slug}",
                PagePaths::child('authors', $slug),
                [
                    'page_type' => PageType::Author,
                    'slug' => $slug,
                    'title' => "{$author->name} — Author Profile | NavyWeek.org",
                    'meta_description' => self::metaFor($author),
                    'og_type' => 'profile',
                    'date_published' => $now,
                    'date_modified' => $now,
                    'is_published' => true,
                ],
                $author,
            );
            $count++;
        }

        return $count;
    }

    /**
     * A concise profile meta description: the author's bio (trimmed) when set, else a
     * generic sentence built from their byline line — never a transcribed fact block.
     */
    private static function metaFor(User $author): string
    {
        if ($author->bio !== null && $author->bio !== '') {
            return Str::limit($author->bio, 155);
        }

        $role = $author->job_title !== null && $author->job_title !== ''
            ? $author->job_title
            : 'editorial contributor';

        return "{$author->name} — {$role} at NavyWeek.org. Profile, areas of expertise, "
            .'and the Navy and veteran-benefit guides they write and review.';
    }
}
