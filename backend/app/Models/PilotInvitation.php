<?php

namespace App\Models;

class PilotInvitation extends TrackerModel
{
    protected $hidden = ['token_hash'];
    protected function casts(): array { return ['expires_at' => 'immutable_datetime', 'accepted_at' => 'immutable_datetime']; }
}
