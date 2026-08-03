<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The links inside a menu. `url` is stored verbatim (root-relative path or an
 * absolute external URL) — nav links merely point at pages, so they are not tied
 * to the page-identity machinery. `parent_id` (self-ref, one level) makes an item
 * a dropdown parent; `target`/`rel` carry external-link attributes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')
                ->constrained('menus')
                ->cascadeOnDelete();
            // Self-referential dropdown parent (one level). Deleting a parent drops
            // its children with it.
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('menu_items')
                ->cascadeOnDelete();
            $table->string('label');
            $table->string('url');
            // e.g. _blank for external links.
            $table->string('target')->nullable();
            // e.g. noopener noreferrer for external links.
            $table->string('rel')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Backs the ordered "top-level items of a menu" read (activeItems:
            // menu_id + parent_id IS NULL, sort_order). Explicit because Postgres
            // does not auto-index foreign keys. (The per-parent activeChildren read
            // is tiny — a handful of rows per menu — so it needs no dedicated index.)
            $table->index(['menu_id', 'parent_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
