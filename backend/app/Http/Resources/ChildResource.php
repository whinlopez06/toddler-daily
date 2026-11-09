<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'display_name' => $this->display_name,
            'first_name' => $this->first_name, 'middle_name' => $this->middle_name,
            'last_name' => $this->last_name, 'suffix' => $this->suffix, 'gender' => $this->gender,
            'birth_date' => $this->birth_date->format('Y-m-d'), 'archived_at' => $this->archived_at?->toIso8601String(),
            'version' => $this->version, 'created_at' => $this->created_at?->toIso8601String()];
    }
}
