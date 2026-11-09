<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\{ChildResource, RecordResource};
use App\Models\{DeletionRequest, Family};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB};

class PrivacyController extends Controller
{
    public function export(Request $r)
    {
        $familyId = $r->user()->family->id;
        return response()->streamDownload(function () use ($familyId) {
            DB::transaction(function () use ($familyId) {
                // A caller-owned transaction (including transactional tests) owns its isolation.
                if (DB::transactionLevel() === 1) {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
                }
                $family = Family::whereKey($familyId)->whereNull('deletion_requested_at')->firstOrFail();
                $children = $family->children()->pluck('id');
                $header = json_encode(['exported_at' => now()->toIso8601String(), 'family' => $family->only(['name','timezone'])], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                echo substr($header, 0, -1);
                $collections = [
                    'children' => \App\Models\Child::where('family_id', $familyId),
                    'timeline_entries' => \App\Models\TimelineEntry::withTrashed()->whereIn('child_id', $children),
                    'growth_measurements' => \App\Models\GrowthMeasurement::withTrashed()->whereIn('child_id', $children),
                    'medications' => \App\Models\Medication::whereIn('child_id', $children),
                    'medication_intakes' => \App\Models\MedicationIntake::whereIn('child_id', $children),
                    'vaccination_records' => \App\Models\VaccinationRecord::withTrashed()->whereIn('child_id', $children),
                    'appointments' => \App\Models\Appointment::whereIn('child_id', $children),
                ];
                foreach ($collections as $name => $query) {
                    echo ','.json_encode($name).':[';
                    $first = true;
                    foreach ($query->orderBy('id')->lazyById(100) as $record) {
                        if (!$first) echo ',';
                        echo json_encode($record instanceof \App\Models\Child ? (new ChildResource($record))->resolve() : (new RecordResource($record))->resolve(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $first = false;
                    }
                    echo ']';
                }
                echo '}';
            });
        }, 'weegrow-export.json', ['Content-Type' => 'application/json', 'Cache-Control' => 'private, no-store']);
    }
    public function destroy(Request $r)
    {
        $r->validate(['family_name' => ['required','string'], 'confirm' => ['accepted']]);
        $user = $r->user();
        DB::transaction(function () use ($r, $user) {
            $family = $user->family()->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($family->name, $r->input('family_name')), 422, 'Enter the family name exactly.');
            $family->update(['deletion_requested_at' => now()]);
            DB::table('reminder_deliveries')->where('user_id', $user->id)->whereIn('status', ['pending','processing'])->update(['status' => 'cancelled']);
            DeletionRequest::firstOrCreate(['family_id' => $family->id], [
                'owner_user_id' => $user->id, 'requested_at' => now(), 'retain_until' => now()->addDays(35),
            ]);
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
        Auth::guard('web')->logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();
        return response()->json(['data' => ['status' => 'pending', 'message' => 'Access is disabled. Erasure will finish after the deletion ledger is safely recorded.']], 202);
    }
}
