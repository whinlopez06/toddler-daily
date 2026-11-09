<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderDelivery extends TrackerModel
{
    protected function casts(): array { return ['due_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime', 'next_attempt_at' => 'immutable_datetime', 'locked_until' => 'immutable_datetime', 'attempts' => 'integer']; }
    public function appointment(): BelongsTo { return $this->belongsTo(Appointment::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
