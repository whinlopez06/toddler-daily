<?php

namespace App\Models;

class AuditLog extends TrackerModel
{
    const UPDATED_AT = null;
    protected function casts(): array { return ['changes_ciphertext' => 'encrypted:array']; }
}
