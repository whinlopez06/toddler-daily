<?php

namespace App\Models;

class DeletionRequest extends TrackerModel
{
    public $timestamps = false;
    protected function casts(): array { return ['requested_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime', 'retain_until' => 'immutable_datetime', 'ledger_confirmed' => 'boolean']; }
}
