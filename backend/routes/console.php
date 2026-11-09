<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\PilotInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Artisan::command('tracker:invite {email : Email address for the invitation} {--hours=48 : Validity in hours, from 1 to 168}', function () {
    $email = mb_strtolower(trim((string) $this->argument('email')));
    $hours = $this->option('hours');
    $validator = Validator::make(['email' => $email, 'hours' => $hours], [
        'email' => ['required', 'email', 'max:254'], 'hours' => ['required', 'integer', 'between:1,168'],
    ]);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());
        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('This address already has an account. Sign in or use password recovery.');
        return 1;
    }
    $token = Str::random(64);
    DB::transaction(function () use ($email, $hours, $token) {
        PilotInvitation::where('email', $email)->whereNull('accepted_at')->delete();
        PilotInvitation::create(['email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours((int) $hours)]);
    });
    $link = rtrim(config('tracker.frontend_url'), '/').'/register?'.http_build_query(['email' => $email, 'token' => $token]);
    $this->info('Invitation created. Share this private, single-use link only with its intended recipient:');
    $this->line($link);
    $this->comment('Expires in '.$hours.' hours. This command does not send email.');
    return 0;
})->purpose('Create an email-bound invitation and print its private registration link');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
