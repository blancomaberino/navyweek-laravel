<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The legacy `/authors/{slug}/` profiles render a structured career timeline — an
 * ordered list of {title, org, period, detail} entries for military service and for
 * civilian career — plus a hero location line, a distinct service/current-title pair,
 * section lead-ins, a curated "writes/reviews for" link list, and a "Profile last
 * reviewed" date. None of it had a home in `users`, so the ported profile page rendered
 * a fraction of the live one. All nullable and editor-owned; an account with no byline
 * simply leaves them empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Hero line 1 (gold): the person's service title — "Captain (O-6), U.S. Navy
            // Reserve" — which is deliberately NOT `job_title` (the NavyWeek byline role).
            $table->string('service_title')->nullable()->after('credentials');
            // Hero line 2 (grey): their current civilian title outside NavyWeek.
            $table->string('current_title')->nullable()->after('service_title');
            $table->string('location_city')->nullable()->after('current_title');
            $table->string('location_state')->nullable()->after('location_city');
            $table->string('location_country')->nullable()->after('location_state');
            // Ordered lists of {title, org, period, detail} — the gold-rule timeline entries.
            $table->json('military_timeline')->nullable()->after('civilian_career');
            $table->json('civilian_timeline')->nullable()->after('military_timeline');
            // The profile page's OWN expertise list. Distinct from `knows_about`, which is
            // the compact byline list the discount guides' Person JSON-LD cites — legacy
            // kept the two apart (DiscountDetail.tsx hardcodes its own four topics).
            $table->json('profile_expertise')->nullable()->after('knows_about');
            // Lead-in sentences above the expertise chips and the works list.
            $table->text('expertise_lead')->nullable()->after('profile_expertise');
            $table->text('works_lead')->nullable()->after('expertise_lead');
            // Curated {url, label, note} links this person is credited on. When set it
            // replaces the auto-derived byline list, which spans every generated page.
            $table->json('featured_works')->nullable()->after('works_lead');
            // "Profile last reviewed: {Month Year}" — the profile's own freshness stamp.
            $table->date('profile_reviewed_at')->nullable()->after('linkedin_url');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
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
            ]);
        });
    }
};
