<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Additional composite indexes described in Section 3 of database-schema.md.
     */
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->index(['pair_id', 'status']);
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->index(['goal_id', 'contributed_at']);
        });

        Schema::table('invites', function (Blueprint $table) {
            $table->index(['code', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropIndex(['pair_id', 'status']);
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropIndex(['goal_id', 'contributed_at']);
        });

        Schema::table('invites', function (Blueprint $table) {
            $table->dropIndex(['code', 'status']);
        });
    }
};
