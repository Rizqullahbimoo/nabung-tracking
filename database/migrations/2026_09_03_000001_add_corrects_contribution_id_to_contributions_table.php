<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "delete" of a deposit is recorded as a new type='correction' row that
     * points back at the deposit it reverses. Keeps the audit trail intact
     * (database-schema.md 2.5).
     */
    public function up(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->foreignId('corrects_contribution_id')
                ->nullable()
                ->constrained('contributions')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('corrects_contribution_id');
        });
    }
};
