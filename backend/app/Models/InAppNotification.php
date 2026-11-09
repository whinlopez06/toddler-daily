<?php

namespace App\Models;

class InAppNotification extends TrackerModel
{
    protected $table = 'notifications';
    protected function casts(): array { return ['data' => 'array', 'read_at' => 'immutable_datetime']; }
}
