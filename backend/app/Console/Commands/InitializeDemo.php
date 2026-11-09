<?php

namespace App\Console\Commands;

use App\Support\DemoEnvironment;
use Illuminate\Console\Command;

class InitializeDemo extends Command
{
    protected $signature = 'tracker:demo-init';
    protected $description = 'Check shared-database demo readiness without changing any data';

    public function handle(DemoEnvironment $environment): int
    {
        if (! $environment->ready()) {
            $this->error('Apply the demo-table migration and explicitly enable DEMO_MODE first.');
            return self::FAILURE;
        }
        $this->info('Demo access is enabled alongside ordinary accounts. No initialization or data copy is required.');
        return self::SUCCESS;
    }
}
