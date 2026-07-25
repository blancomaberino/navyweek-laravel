<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The connection's default affiliate network — deferred from the Connections slice
 * (#420) so the column and its FK constraint land together, now that
 * `affiliate_networks` exists. An offer without its own network falls back to this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->foreignId('default_affiliate_network_id')
                ->nullable()
                ->after('logo_url')
                ->constrained('affiliate_networks')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_affiliate_network_id');
        });
    }
};
