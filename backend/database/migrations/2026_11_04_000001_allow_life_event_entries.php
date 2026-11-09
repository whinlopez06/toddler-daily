<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE timeline_entries DROP CONSTRAINT timeline_entries_kind_check');
        DB::statement("ALTER TABLE timeline_entries ADD CONSTRAINT timeline_entries_kind_check CHECK (kind IN ('memory','milestone','health_note','life_event'))");
    }

    public function down(): void
    {
        // Fail before changing the constraint if rollback would invalidate saved entries.
        if (DB::table('timeline_entries')->where('kind', 'life_event')->exists()) {
            throw new RuntimeException('Life event entries must be reclassified before rolling back.');
        }
        DB::statement('ALTER TABLE timeline_entries DROP CONSTRAINT timeline_entries_kind_check');
        DB::statement("ALTER TABLE timeline_entries ADD CONSTRAINT timeline_entries_kind_check CHECK (kind IN ('memory','milestone','health_note'))");
    }
};
