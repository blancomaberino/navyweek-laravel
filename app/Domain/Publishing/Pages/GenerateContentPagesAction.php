<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Pages;

use App\Domain\Publishing\Enums\PageType;
use App\Domain\Publishing\Repositories\PageRepositoryInterface;
use Illuminate\Support\Carbon;

/**
 * Seeds the DB-driven **content** pages with their initial CMS body — the editorial
 * pages whose prose lives in `pages.body_blocks` (editable in Filament), not derived
 * from a data registry. This foundation slice ships the Breadcrumb-only legal/utility
 * pages (`/privacy/`, `/terms/`, `/contact/`); the richer YMYL pages (veterans-day,
 * va-disability, veterans-home-care) land in follow-up slices with their Article/Person/
 * WebPage graphs.
 *
 * Idempotent: upserts by url_path, so the build clock preserves `date_published` and an
 * editor's later body edits are NOT clobbered on re-run — the seed only establishes the
 * initial content for a page that doesn't exist yet (a present page keeps its body).
 */
final class GenerateContentPagesAction
{
    public function __construct(
        private readonly PageRepositoryInterface $pages,
    ) {}

    public function __invoke(): int
    {
        $now = Carbon::now();
        $count = 0;

        foreach ($this->seedPages() as $seed) {
            $page = $this->pages->findPublishedByPath($seed['url_path']);

            // Don't clobber an editor's body on re-run — only seed a page that's new or
            // has no body yet.
            $attributes = [
                'page_type' => PageType::Static,
                'slug' => $seed['slug'],
                'title' => $seed['title'],
                'meta_description' => $seed['meta'],
                'date_published' => $now,
                'date_modified' => $now,
                'is_published' => true,
            ];
            if ($page === null || $page->body_blocks === null || $page->body_blocks === []) {
                $attributes['body_blocks'] = $seed['blocks'];
            }

            $this->pages->upsertPillarPage($seed['url_path'], $attributes);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{url_path: string, slug: string, title: string, meta: string, blocks: list<array<string, mixed>>}>
     */
    private function seedPages(): array
    {
        return [
            [
                'url_path' => '/privacy/',
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta' => 'How NavyWeek.org handles data and privacy.',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'NavyWeek.org is an independent editorial publisher. This policy explains what we collect and how we use it. Editors can update this content in the admin panel.'],
                    ['type' => 'heading', 'text' => 'What we collect'],
                    ['type' => 'paragraph', 'text' => 'We use privacy-respecting analytics to understand aggregate traffic. We do not sell personal information.'],
                ],
            ],
            [
                'url_path' => '/terms/',
                'slug' => 'terms',
                'title' => 'Terms of Use',
                'meta' => 'The terms governing use of NavyWeek.org.',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'By using NavyWeek.org you agree to these terms. NavyWeek.org is not affiliated with the U.S. Navy, NAVCO, or any brand listed. Editors can update this content in the admin panel.'],
                    ['type' => 'heading', 'text' => 'Editorial independence'],
                    ['type' => 'paragraph', 'text' => 'Coverage is decided by editorial judgment and verifiable facts, never by affiliate arrangements.'],
                ],
            ],
            [
                'url_path' => '/contact/',
                'slug' => 'contact',
                'title' => 'Contact',
                'meta' => 'How to reach the NavyWeek.org editorial team.',
                'blocks' => [
                    ['type' => 'paragraph', 'text' => 'Questions or corrections? We welcome reader feedback, especially on discount accuracy. Editors can update this content in the admin panel.'],
                ],
            ],
        ];
    }
}
