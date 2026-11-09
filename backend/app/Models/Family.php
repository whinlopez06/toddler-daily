<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Family extends TrackerModel
{
    protected $attributes = ['version' => 1, 'timezone' => 'Asia/Manila'];
    protected function casts(): array { return ['deletion_requested_at' => 'immutable_datetime', 'version' => 'integer']; }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function children(): HasMany { return $this->hasMany(Child::class); }
}
