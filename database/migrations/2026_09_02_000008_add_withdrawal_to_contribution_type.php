<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow "withdrawal" rows in contributions.type (money taken out of a
     * goal). Stored with a positive amount; subtracted when computing balance.
     *
     * Laravel's enum()->change() emits invalid SQL on PostgreSQL, so the
     * CHECK constraint is swapped by hand there; other drivers use the
     * schema builder (which rebuilds the table).
     */
    private array $types = ['deposit', 'withdrawal', 'correction'];

    private array $previousTypes = ['deposit', 'correction'];

    public function up(): void
    {
        $this->setAllowedTypes($this->types);
    }

    public function down(): void
    {
        $this->setAllowedTypes($this->previousTypes);
    }

    private function setAllowedTypes(array $types): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $list = "'".implode("', '", $types)."'";

            DB::statement('ALTER TABLE contributions DROP CONSTRAINT IF EXISTS contributions_type_check');
            DB::statement("ALTER TABLE contributions ADD CONSTRAINT contributions_type_check CHECK (type IN ({$list}))");

            return;
        }

        Schema::table('contributions', function (Blueprint $table) use ($types) {
            $table->enum('type', $types)->default('deposit')->change();
        });
    }
};
