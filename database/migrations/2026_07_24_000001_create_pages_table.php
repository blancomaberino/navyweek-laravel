<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The central routing/SEO layer. Every public route is a row keyed on `url_path`
 * (the full canonical path with leading + trailing slash). This slice creates the
 * routing-critical columns only; Phase 2 extends the table with the full SEO /
 * JSON-LD / body columns and the polymorphic `pageable` relation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_type')->index();
            $table->string('slug')->index();
            // The routing key — unique canonical path, always "/…/".
            $table->string('url_path')->unique();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
