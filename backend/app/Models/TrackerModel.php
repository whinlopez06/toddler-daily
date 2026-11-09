<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

abstract class TrackerModel extends Model
{
    use HasUlids;
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:s.uP';
}
