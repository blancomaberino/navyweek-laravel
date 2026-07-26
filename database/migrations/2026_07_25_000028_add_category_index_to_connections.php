<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index `connections.category`. The discount category hubs (slice 9) read every
 * connection in a category via `EloquentDiscountCategoryRepository::orderedConnections`
 * (`where('category', …)`); without this index that read is a full scan of the
 * ~15k-row connections table (active brands + the backlog queue).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropIndex(['category']);
        });
    }
};
