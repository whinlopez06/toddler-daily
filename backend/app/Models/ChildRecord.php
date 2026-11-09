<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class ChildRecord extends TrackerModel
{
    public function child(): BelongsTo { return $this->belongsTo(Child::class); }
}
