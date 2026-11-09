<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

class MedicationIntake extends ChildRecord
{
    const UPDATED_AT = null;
    protected $hidden = ['request_hash', 'client_request_id'];
    protected function casts(): array { return ['administered_at' => 'immutable_datetime', 'snapshot' => 'encrypted:array', 'notes' => 'encrypted', 'actual_amount' => 'decimal:3']; }
    public function replacement(): HasOne { return $this->hasOne(self::class, 'correction_of_id'); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Append a correction instead of changing intake history.'));
        static::deleting(fn () => throw new LogicException('Intake history can only be erased through family deletion.'));
    }
}
