<?php

namespace App\Console\Commands;

use App\Models\DemoAccount;
use App\Support\DemoEnvironment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DemoReport extends Command
{
    protected $signature = 'tracker:demo-report {--limit=50 : Most recent accounts, maximum 200} {--events : Include recent login events}';

    protected $description = 'Operator-only terminal report of unverified demo emails, expiry and access events';

    public function handle(DemoEnvironment $environment): int
    {
        if (! $environment->ready()) {
            $this->error('Demo access must be configured and enabled to use this command.');

            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 200]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 200.');

            return self::FAILURE;
        }
        $accounts = DemoAccount::with('user')->orderByDesc('started_at')->orderByDesc('user_id')->limit($limit)->get();
        $this->table(['Account ID', 'Supplied email (unverified)', 'Started UTC', 'Expires UTC', 'Last login UTC', 'Access'],
            $accounts->map(fn ($demo) => [$demo->user_id, $demo->user->email, $demo->started_at->toDateTimeString(),
                $demo->expires_at->toDateTimeString(), $demo->last_login_at?->toDateTimeString() ?? 'Never',
                $demo->revoked_at ? 'Revoked' : ($demo->active() ? 'Active' : 'Expired')])->all());
        if ($this->option('events')) {
            $this->table(['Account ID', 'Event', 'Time UTC'], DB::table('demo_access_events')->whereIn('user_id', $accounts->pluck('user_id'))
                ->orderByDesc('id')->limit(200)->get(['user_id', 'event', 'occurred_at'])->map(fn ($event) => (array) $event)->all());
        }

        return self::SUCCESS;
    }
}
