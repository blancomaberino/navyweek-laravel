<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lead paragraphs each discount guide opens with, above the credit-cards
 * callout. Distinct from `details` ("How it works"), which sits further down.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->json('intro')->nullable()->after('discount_summary');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->dropColumn('intro');
        });
    }
};
