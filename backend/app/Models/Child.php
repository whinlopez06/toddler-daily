<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Child extends TrackerModel
{
    protected $attributes = ['version' => 1];
    protected function casts(): array { return ['birth_date' => 'immutable_date:Y-m-d', 'archived_at' => 'immutable_datetime', 'version' => 'integer']; }
    public function family(): BelongsTo { return $this->belongsTo(Family::class); }
    public function timelineEntries(): HasMany { return $this->hasMany(TimelineEntry::class); }
}
