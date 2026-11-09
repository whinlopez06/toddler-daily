<?php

namespace App\Console\Commands;

use App\Models\PilotInvitation;
use Illuminate\Console\Command;

class IssueInvitation extends Command
{
    protected $signature = 'app:invite {email} {--days=7}';
    protected $description = 'Create a single-use pilot invitation; it does not send email.';
    public function handle(): int
    {
        $email = mb_strtolower(trim($this->argument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || (int) $this->option('days') < 1 || (int) $this->option('days') > 30) {
            $this->error('Provide a valid email and a lifetime of 1–30 days.');
            return self::FAILURE;
        }
        $token = bin2hex(random_bytes(32));
        PilotInvitation::create(['email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays((int) $this->option('days'))]);
        $this->line(rtrim(config('tracker.frontend_url'), '/').'/register?token='.$token.'&email='.rawurlencode($email));
        $this->comment('Share this single-use invitation privately with its intended recipient.');
        return self::SUCCESS;
    }
}
