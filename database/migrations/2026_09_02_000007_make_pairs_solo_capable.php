<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "pair" can now represent a single person (solo mode): the second
     * member and the pairing timestamp are optional until someone joins.
     */
    public function up(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_two_id')->nullable()->change();
            $table->timestamp('paired_at')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pairs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_two_id')->nullable(false)->change();
            $table->timestamp('paired_at')->nullable(false)->change();
        });
    }
};
