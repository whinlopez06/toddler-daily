<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'email' => $this->email,
            'email_verified' => $this->email_verified_at !== null,
            'is_demo' => $this->demoAccount !== null,
            'demo_expires_at' => $this->demoAccount?->expires_at?->toIso8601String(),
            'email_reminders_enabled' => $this->email_reminders_enabled,
            'family' => $this->family ? [
                'id' => $this->family->id, 'name' => $this->family->name, 'timezone' => $this->family->timezone,
                'version' => $this->family->version, 'deletion_pending' => $this->family->deletion_requested_at !== null,
            ] : null,
        ];
    }
}
