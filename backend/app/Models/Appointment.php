<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends ChildRecord
{
    protected $attributes = ['version' => 1, 'schedule_version' => 1, 'kind' => 'general', 'status' => 'scheduled', 'reminders_enabled' => true];
    protected function casts(): array { return ['starts_at' => 'immutable_datetime', 'reason' => 'encrypted', 'visit_summary' => 'encrypted', 'reminders_enabled' => 'boolean', 'schedule_version' => 'integer', 'version' => 'integer']; }
    public function deliveries(): HasMany { return $this->hasMany(ReminderDelivery::class); }
}
