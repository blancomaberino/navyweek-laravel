<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * U.S. state lookup (50 states + DC) — port of `bases/states.ts`. The state-based
 * base hubs (`/bases/<state>/`) group on it; `bases.state` is a soft slug FK here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('us_states', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('abbr', 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('us_states');
    }
};
