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
                // Bio prose migrated verbatim from the legacy AuthorTAlford profile page.
                'bio' => 'T Madden Alford is a U.S. Naval Academy graduate, a Captain (O-6) in the '
                    .'U.S. Navy Reserve, and a former submarine officer who served aboard the '
                    .'fast-attack submarine USS Key West (SSN-722). After active duty he managed '
                    ."the Defense Threat Reduction Agency's Nuclear Weapon Surety Program, then "
                    .'moved into the private sector with leadership roles at ExxonMobil and XTO '
                    .'Energy before co-founding Triton Well Services, Cloud Clinic, and Honest '
                    .'Paws. He writes for NavyWeek.org on Navy service, veteran benefits, and '
                    .'topics that connect the two.',
                'linkedin_url' => 'https://www.linkedin.com/in/t-madden-alford-8281b04',
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
            ],
        );

        $reviewer = User::query()->updateOrCreate(
            ['email' => 'erik.rivera@navyweek.org'],
            [
                'name' => 'Erik Rivera',
                'slug' => Config::string('site.editorial.default_reviewer_slug'),
                'job_title' => 'Expert Reviewer, NavyWeek.org',
                'credentials' => "U.S. Naval Academy '04 · Former U.S. Navy Explosive Ordnance Disposal (EOD) officer",
                'avatar_path' => '/authors/erik-rivera.jpg',
                'knows_about' => [
                    'U.S. Navy service & culture',
                    'Naval Explosive Ordnance Disposal (EOD)',
                    'Naval Special Operations',
                    'Officer career paths',
                    'U.S. Naval Academy',
                    'Veteran entrepreneurship',
                ],
                // Bio prose migrated verbatim from the legacy AuthorErikRivera profile page.
                'bio' => 'Erik Rivera is a U.S. Naval Academy graduate (Class of 2004, B.S. in '
                    .'Weapons & Systems Engineering) and a former U.S. Navy Explosive Ordnance '
                    .'Disposal (EOD) / Naval Special Operations officer. After his Navy service '
                    .'he became an entrepreneur and investor — he is the CEO of OnePet and a '
                    .'founder of Honest Paws and CertaPet, and was an early investor in Sellbrite '
                    .'and AdEspresso. Based in San Juan, Puerto Rico, he reviews NavyWeek.org '
                    .'reference content for accuracy and plain-language clarity, drawing on his '
                    .'firsthand experience as a commissioned U.S. Navy officer.',
                'linkedin_url' => 'https://www.linkedin.com/in/erik-rivera',
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
