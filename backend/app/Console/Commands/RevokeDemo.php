<?php

namespace App\Console\Commands;

use App\Models\DemoAccount;
use App\Support\DemoEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeDemo extends Command
{
    protected $signature = 'tracker:demo-revoke {user : Demo account ID}';

    protected $description = 'Revoke a demo account without deleting its history';

    public function handle(DemoEnvironment $environment): int
    {
        if (! $environment->ready()) {
            $this->error('Demo access must be configured and enabled to use this command.');

            return self::FAILURE;
        }
        $demo = DemoAccount::find($this->argument('user'));
        if (! $demo) {
            $this->error('Demo account not found.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($demo) {
            if (DemoAccount::whereKey($demo->user_id)->whereNull('revoked_at')->update(['revoked_at' => now()])) {
                $demo->recordEvent('revoked');
            }
            DB::table('sessions')->where('user_id', $demo->user_id)->delete();
        });
        $this->info('Demo access revoked. Account and visit history retained.');

        return self::SUCCESS;
    }
}
