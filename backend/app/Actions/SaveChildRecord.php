<?php

namespace App\Actions;

use App\Actions\Appointments\PlanReminders;
use App\Models\{Appointment, Child, ChildRecord, Medication, User};
use App\Support\RecordTypes;
use Illuminate\Support\Facades\DB;

class SaveChildRecord
{
    public function handle(User $user, Child $child, string $type, array $data, ?ChildRecord $record = null): ChildRecord
    {
        return DB::transaction(function () use ($user, $child, $type, $data, $record) {
            $demo = \App\Support\DemoQuota::lock($user);
            if (!$record) {
                \App\Support\DemoQuota::record($demo);
            }
            $child = Child::whereKey($child->id)->lockForUpdate()->firstOrFail();
            abort_if($child->archived_at !== null, 409, 'Restore this child before adding or editing records.');
            [$class, , $fields] = RecordTypes::get($type);
            $before = [];
            if ($record) {
                $record = $class::whereKey($record->id)->lockForUpdate()->firstOrFail();
                abort_if($record->version !== $data['version'], 409, 'This record was changed. Reload before saving.');
                $before = $record->attributesToArray();
            }
            $values = array_intersect_key($data, array_flip($fields));
            if ($type === 'medications' && array_key_exists('archived', $data)) {
                $values['archived_at'] = $data['archived'] ? now() : null;
            }
            if (!$record) $record = new $class(['child_id' => $child->id, 'created_by' => $user->id]);
            $record->fill($values);
            if ($record instanceof Appointment) {
                $changesSchedule = !$record->exists || $record->isDirty(['starts_at','status','reminders_enabled','timezone']);
                if ($record->exists && $changesSchedule) $record->schedule_version++;
            }
            if ($record->exists) $record->version++;
            $record->save();
            if ($record instanceof Appointment) app(PlanReminders::class)->handle($record);
            app(WriteAudit::class)->handle($user->family, $user->id, $record, $before ? 'updated' : 'created', $before);
            return $record;
        });
    }
}
