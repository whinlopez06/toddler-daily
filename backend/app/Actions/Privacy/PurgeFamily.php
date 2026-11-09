<?php

namespace App\Actions\Privacy;

use App\Models\DeletionRequest;
use Illuminate\Support\Facades\DB;

class PurgeFamily
{
    public function handle(DeletionRequest $request): void
    {
        app(WriteDeletionLedger::class)->handle($request);
        DB::transaction(function () use ($request) {
            DB::statement("SET LOCAL statement_timeout = '12000ms'");
            DB::statement("SET LOCAL app.privacy_purge = 'on'");
            $family = DB::table('families')->where('id', $request->family_id)->lockForUpdate()->first();
            if (!$family) {
                $request->update(['status' => 'completed', 'completed_at' => now(), 'ledger_confirmed' => true, 'last_error_code' => null]);
                return;
            }
            $children = DB::table('children')->where('family_id', $family->id)->pluck('id');
            $appointments = DB::table('appointments')->whereIn('child_id', $children)->pluck('id');
            DB::table('notifications')->where('user_id', $family->owner_user_id)->delete();
            DB::table('reminder_deliveries')->whereIn('appointment_id', $appointments)->delete();
            DB::table('audit_logs')->where('family_id', $family->id)->delete();
            foreach (['medication_intakes','appointments','medications','vaccination_records','growth_measurements','timeline_entries'] as $table) {
                DB::table($table)->whereIn('child_id', $children)->delete();
            }
            DB::table('children')->whereIn('id', $children)->delete();
            DB::table('sessions')->where('user_id', $family->owner_user_id)->delete();
            $email = DB::table('users')->where('id', $family->owner_user_id)->value('email');
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('pilot_invitations')->where('email', $email)->delete();
            DB::table('families')->where('id', $family->id)->delete();
            DB::table('users')->where('id', $family->owner_user_id)->delete();
            $request->update(['status' => 'completed', 'completed_at' => now(), 'ledger_confirmed' => true, 'last_error_code' => null]);
        });
    }
}
