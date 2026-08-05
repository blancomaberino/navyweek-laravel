<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Shared\Import\SeedArtifact;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Imports the public author-profile detail the legacy `/authors/{slug}/` pages
 * carried — the structured military/civilian timelines, the hero service +
 * current-title lines, the location, the section lead-ins, the curated works list,
 * and the "Profile last reviewed" date — from a committed seed artifact keyed by
 * the user's profile slug. Idempotent; only fills columns that are still empty
 * unless --force.
 */
final class ImportAuthorProfilesCommand extends Command
{
    protected $signature = 'import:author-profiles {artifact=author-profiles} {--force : Overwrite existing values}';

    protected $description = 'Import the structured author-profile detail (timelines, titles, location) onto each byline user from a seed artifact';

    /** Columns the artifact owns, in the order they appear on the profile page. */
    private const COLUMNS = [
        'service_title',
        'current_title',
        'location_city',
        'location_state',
        'location_country',
        'military_timeline',
        'civilian_timeline',
        'profile_expertise',
        'expertise_lead',
        'works_lead',
        'featured_works',
        'profile_reviewed_at',
    ];

    public function handle(): int
    {
        /** @var array<string, array<string, mixed>> $bySlug */
        $bySlug = SeedArtifact::read((string) $this->argument('artifact'));
        $force = (bool) $this->option('force');
        $filled = 0;

        foreach ($bySlug as $slug => $profile) {
            $user = User::query()->where('slug', $slug)->first();

            if (! $user instanceof User) {
                $this->warn("No user with slug {$slug} — skipped.");

                continue;
            }

            $updates = [];

            foreach (self::COLUMNS as $column) {
                $value = $profile[$column] ?? null;

                if ($value === null || $value === [] || (filled($user->getAttribute($column)) && ! $force)) {
                    continue;
                }

                $updates[$column] = $value;
            }

            if ($updates === []) {
                continue;
            }

            $user->forceFill($updates)->save();
            $filled++;
        }

        $this->info("Imported author profile detail onto {$filled} users.");

        return self::SUCCESS;
    }
}
