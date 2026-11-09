<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class TimelineEntry extends ChildRecord
{
    protected $attributes = ['version' => 1];
    use SoftDeletes;
    protected function casts(): array { return ['description' => 'encrypted', 'entry_date' => 'immutable_date:Y-m-d', 'version' => 'integer']; }
}
