<?php

namespace App\Actions\Accounts;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    public function reset($user, array $input): void
    {
        if ($user->demoAccount) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Password recovery is unavailable for demo accounts.']);
        }
        Validator::make($input, ['password' => ['required', 'string', 'max:128', Password::min(12), 'confirmed']])->validate();
        $user->forceFill(['password' => $input['password']])->save();
        \Illuminate\Support\Facades\DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}
