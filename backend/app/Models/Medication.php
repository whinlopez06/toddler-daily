<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Medication extends ChildRecord
{
    protected $attributes = ['version' => 1];
    protected function casts(): array { return ['instructions' => 'encrypted', 'start_date' => 'immutable_date:Y-m-d', 'end_date' => 'immutable_date:Y-m-d', 'instructed_amount' => 'decimal:3', 'archived_at' => 'immutable_datetime', 'version' => 'integer']; }
    public function intakes(): HasMany { return $this->hasMany(MedicationIntake::class); }
}
