<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $fields = ['id', 'child_id', 'version', 'created_at', 'updated_at', 'deleted_at',
            'kind', 'category', 'title', 'description', 'entry_date', 'measured_on', 'weight_value', 'weight_unit',
            'height_value', 'height_unit', 'notes', 'name', 'instructed_amount', 'dose_unit', 'instructions',
            'start_date', 'end_date', 'archived_at', 'vaccine_name', 'administered_on', 'dose_label', 'provider',
            'starts_at', 'timezone', 'clinic', 'reason', 'visit_summary', 'status', 'reminders_enabled',
            'medication_id', 'administered_at', 'actual_amount', 'snapshot', 'state', 'correction_of_id', 'correction_reason'];
        $data = array_intersect_key($this->resource->attributesToArray(), array_flip($fields));
        if ($this->resource->relationLoaded('child')) {
            $data['child_name'] = $this->resource->child->display_name;
        }
        if ($this->resource->relationLoaded('replacement')) {
            $data['replacement_id'] = $this->resource->replacement?->id;
        }
        return $data;
    }
}
