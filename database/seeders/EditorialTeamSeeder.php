<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Publishing\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the two editorial `users` who form the default byline — the E-E-A-T author
 * + reviewer the legacy discount pages cited, now data instead of a code constant.
 * Their profile slugs match `config('site.editorial.*')`, so the importer and this
 * seeder agree on the default assignment.
 *
 * Idempotent: users are upserted by email, and every page missing an author/reviewer
 * is back-filled with the default byline (so pages imported before this seeder ran
 * also get assigned). Passwords are random + unusable — these are byline profiles,
 * not interactive logins; grant panel access and set a real password out of band.
 */
class EditorialTeamSeeder extends Seeder
{
    public function run(): void
    {
        // The profile slugs come from config, so the seeded users ARE the default
        // byline the importer resolves (`config('site.editorial.*')`) — one definition,
        // no drift between seeder and importer.
        $author = User::query()->updateOrCreate(
            ['email' => 'madden.alford@navyweek.org'],
            [
                'name' => 'T Madden Alford',
                'slug' => Config::string('site.editorial.default_author_slug'),
                'job_title' => 'Editor, NavyWeek.org',
                'credentials' => "U.S. Naval Academy '02 · U.S. Navy Reserve Captain (O-6) · Former submarine officer, USS Key West",
                'avatar_path' => '/authors/t-alford.jpg',
                'knows_about' => ['military discounts', 'veteran benefits', 'U.S. Navy', 'ID.me verification'],
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ],
        );

        $reviewer = User::query()->updateOrCreate(
            ['email' => 'erik.rivera@navyweek.org'],
            [
                'name' => 'Erik Rivera',
                'slug' => Config::string('site.editorial.default_reviewer_slug'),
                'credentials' => "U.S. Naval Academy '04 · Former U.S. Navy Explosive Ordnance Disposal (EOD) officer",
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ],
        );

        // Back-fill any pages that were imported before the byline existed. Only
        // fills the gaps — a per-page admin override is never clobbered.
        Page::query()->whereNull('author_id')->update(['author_id' => $author->id]);
        Page::query()->whereNull('reviewer_id')->update(['reviewer_id' => $reviewer->id]);
    }
}
