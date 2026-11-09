<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class VaccinationRecord extends ChildRecord
{
    protected $attributes = ['version' => 1];
    use SoftDeletes;
    protected function casts(): array { return ['notes' => 'encrypted', 'administered_on' => 'immutable_date:Y-m-d', 'version' => 'integer']; }
}
