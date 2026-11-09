<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class GrowthMeasurement extends ChildRecord
{
    protected $attributes = ['version' => 1];
    use SoftDeletes;
    protected function casts(): array { return ['notes' => 'encrypted', 'measured_on' => 'immutable_date:Y-m-d', 'weight_value' => 'decimal:3', 'height_value' => 'decimal:2', 'version' => 'integer']; }
}
