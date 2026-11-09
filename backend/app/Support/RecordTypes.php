<?php

namespace App\Support;

use App\Models\{Appointment, GrowthMeasurement, Medication, TimelineEntry, VaccinationRecord};

class RecordTypes
{
    public static function get(string $type): array
    {
        return match ($type) {
            'timeline-entries' => [TimelineEntry::class, 'entry_date', ['kind','category','title','description','entry_date']],
            'growth-measurements' => [GrowthMeasurement::class, 'measured_on', ['measured_on','weight_value','weight_unit','height_value','height_unit','notes']],
            'medications' => [Medication::class, 'created_at', ['name','instructed_amount','dose_unit','instructions','start_date','end_date','archived_at']],
            'vaccination-records' => [VaccinationRecord::class, 'administered_on', ['vaccine_name','administered_on','dose_label','provider','notes']],
            'appointments' => [Appointment::class, 'starts_at', ['title','kind','starts_at','timezone','provider','clinic','reason','visit_summary','status','reminders_enabled']],
            default => abort(404),
        };
    }
}
