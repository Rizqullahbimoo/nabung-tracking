<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * PostgreSQL does not auto-create indexes for foreign key columns. The
     * composite indexes in 2026_09_02_000005 already cover goals.pair_id and
     * contributions.goal_id; this migration adds the remaining single-column
     * foreign keys used for lookups / joins.
     */
    public function up(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->index('proposed_by');
            $table->index('approved_by');
        });

        Schema::table('pairs', function (Blueprint $table) {
            $table->index('user_one_id');
            $table->index('user_two_id');
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('goals', function (Blueprint $table) {
            $table->dropIndex(['proposed_by']);
            $table->dropIndex(['approved_by']);
        });

        Schema::table('pairs', function (Blueprint $table) {
            $table->dropIndex(['user_one_id']);
            $table->dropIndex(['user_two_id']);
        });

        Schema::table('contributions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
