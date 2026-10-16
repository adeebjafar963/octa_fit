<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Allow multiple versions to share the same plan_uuid.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // Recreate unique as composite via table rebuild is heavy; drop lone unique index if present.
            $indexes = DB::select("PRAGMA index_list('nutrition_plans')");
            foreach ($indexes as $index) {
                $name = $index->name ?? '';
                if ($name === 'nutrition_plans_plan_uuid_unique') {
                    DB::statement('DROP INDEX IF EXISTS nutrition_plans_plan_uuid_unique');
                }
            }
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS nutrition_plans_plan_uuid_version_unique ON nutrition_plans (plan_uuid, version)');

            return;
        }

        try {
            Schema::table('nutrition_plans', function ($table) {
                $table->dropUnique(['plan_uuid']);
            });
        } catch (\Throwable) {
            // Index may already be absent.
        }

        Schema::table('nutrition_plans', function ($table) {
            $table->unique(['plan_uuid', 'version'], 'nutrition_plans_plan_uuid_version_unique');
        });
    }

    public function down(): void
    {
        // no-op — composite unique is the correct model
    }
};
