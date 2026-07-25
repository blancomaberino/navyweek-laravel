<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keyword-variant slugs that resolve to a canonical Connection. Replaces the
 * legacy `pipeline/queue/aliases.json` map (alias_slug → canonical slug). The
 * canonical connection is marked `duplicate` with `duplicate_of` set; requests
 * to an alias slug resolve here to the surviving brand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connection_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias_slug')->unique();
            $table->foreignId('connection_id')
                ->constrained('connections')
                ->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connection_aliases');
    }
};
