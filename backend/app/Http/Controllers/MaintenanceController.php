<?php

namespace App\Http\Controllers;

use App\Actions\ProcessReminders;
use App\Actions\Privacy\PurgeFamily;
use App\Models\DeletionRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class MaintenanceController extends Controller
{
    public function __invoke(ProcessReminders $reminders)
    {
        $lock = Cache::lock('maintenance-tick', 120);
        if (!$lock->get()) return response()->json(['data' => ['busy' => true]], 202);
        $deadline = microtime(true) + 20;
        try {
            $counts = $reminders->handle(null, $deadline - 12);
            $deletion = DeletionRequest::where('status', 'pending')->orderBy('requested_at')->first();
            $purged = 0;
            if ($deletion && microtime(true) < $deadline - 12) {
                try {
                    app(PurgeFamily::class)->handle($deletion);
                    $purged = 1;
                } catch (Throwable) {
                    $deletion->update(['last_error_code' => 'purge_pending_review']);
                }
            }
            // Bounded cleanup, with personal histories retained until deliberate family erasure.
            DB::table('notifications')->whereIn('id', DB::table('notifications')->where('created_at', '<', now()->subDays(90))->limit(100)->pluck('id'))->delete();
            DB::table('pilot_invitations')->where('expires_at', '<', now()->subDays(7))->delete();
            DeletionRequest::where('status', 'completed')->where('retain_until', '<', now())->delete();
            Cache::put('maintenance:last-success', now()->toIso8601String(), now()->addDays(7));
            return response()->json(['data' => [...$counts, 'purged' => $purged]]);
        } finally {
            $lock->release();
        }
    }
}
