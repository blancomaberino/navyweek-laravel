<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The author profile pages carry "Military service" and "Civilian career"
 * sections that had no home in the schema, so they never rendered. Both are
 * editor-owned prose on the user's profile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('military_service')->nullable()->after('credentials');
            $table->text('civilian_career')->nullable()->after('military_service');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['military_service', 'civilian_career']);
        });
    }
};
