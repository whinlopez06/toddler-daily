<?php

namespace App\Actions\Medications;

use App\Actions\WriteAudit;
use App\Models\{Child, Medication, MedicationIntake, User};
use Illuminate\Support\Facades\DB;

class RecordIntake
{
    public function handle(User $user, Medication $medication, array $data, ?string $predecessor = null): MedicationIntake
    {
        return DB::transaction(function () use ($user, $medication, $data, $predecessor) {
            $demo = \App\Support\DemoQuota::lock($user);
            $child = Child::whereKey($medication->child_id)->lockForUpdate()->firstOrFail();
            $medication = Medication::whereKey($medication->id)->lockForUpdate()->firstOrFail();
            abort_if($child->archived_at !== null, 409, 'Restore this child before recording an administration.');
            $canonical = [...$data, 'medication_id' => $medication->id, 'correction_of_id' => $predecessor];
            ksort($canonical);
            $hash = hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR));
            $existing = MedicationIntake::where('child_id', $child->id)->where('client_request_id', $data['client_request_id'])->first();
            if ($existing) {
                abort_unless(hash_equals($existing->request_hash, $hash), 409, 'This request identifier was already used with different information.');
                return $existing;
            }
            \App\Support\DemoQuota::record($demo);
            $previous = null;
            if ($predecessor) {
                $previous = MedicationIntake::where('child_id', $child->id)->where('medication_id', $medication->id)->whereKey($predecessor)->lockForUpdate()->firstOrFail();
                abort_if($previous->replacement()->exists(), 409, 'This administration already has a correction. Reload its history.');
            } else {
                abort_if($medication->archived_at !== null, 409, 'Restore this medication before recording an administration.');
            }
            $record = MedicationIntake::create([
                ...$data, 'child_id' => $child->id, 'medication_id' => $medication->id, 'created_by' => $user->id,
                'correction_of_id' => $predecessor, 'request_hash' => $hash,
                'snapshot' => $previous?->snapshot ?? $medication->only(['name','instructions','instructed_amount','dose_unit','start_date','end_date']),
            ]);
            app(WriteAudit::class)->handle($user->family, $user->id, $record, $predecessor ? 'corrected' : 'created');
            return $record->load('replacement');
        });
    }
}
