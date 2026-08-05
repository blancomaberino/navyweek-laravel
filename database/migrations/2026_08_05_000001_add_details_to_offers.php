<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The "How it works" narrative on each discount guide — a short list of
 * paragraphs explaining the verification flow and the offer's context. Present
 * on the published site but never imported, so the section never rendered.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->json('details')->nullable()->after('discount_summary');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table): void {
            $table->dropColumn('details');
        });
    }
};
