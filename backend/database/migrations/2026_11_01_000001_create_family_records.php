<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql' || DB::selectOne('SELECT current_schema() AS name')->name !== 'app') {
            throw new RuntimeException('Provision the private app schema and use PostgreSQL before migrating.');
        }
        DB::unprepared(file_get_contents(database_path('schema/family-records.sql')));
    }

    public function down(): void
    {
        foreach (['deletion_requests', 'audit_logs', 'notifications', 'reminder_deliveries', 'appointments', 'vaccination_records', 'medication_intakes', 'medications', 'growth_measurements', 'timeline_entries', 'children', 'families', 'pilot_invitations'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::statement('DROP FUNCTION IF EXISTS app.freeze_medication_intake()');
    }
};
