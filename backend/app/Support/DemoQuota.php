<?php

namespace App\Support;

use App\Models\DemoAccount;
use App\Models\User;

class DemoQuota
{
    public static function lock(User $user): ?DemoAccount
    {
        return DemoAccount::whereKey($user->id)->lockForUpdate()->first();
    }

    public static function record(?DemoAccount $demo): void
    {
        if ($demo) {
            abort_if($demo->records_created >= config('demo.records'), 409, 'This demo has reached its limit of 100 added records.');
            $demo->increment('records_created');
        }
    }
}
