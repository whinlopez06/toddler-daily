<?php

namespace App\Actions\Accounts;

use App\Models\Family;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function create(array $input): User
    {
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'max:128', Password::min(12), 'confirmed'],
            'family_name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone:all'],
            'privacy_accepted' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'],
                'privacy_notice_version' => config('tracker.privacy_notice_version'), 'privacy_accepted_at' => now(),
            ]);
            Family::create(['owner_user_id' => $user->id, 'name' => $data['family_name'], 'timezone' => $data['timezone']]);
            return $user;
        });
    }
}
