<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class DemoEnvironment
{
    public function ready(): bool
    {
        return (bool) config('demo.enabled')
            && Schema::hasTable('demo_accounts')
            && Schema::hasTable('demo_access_events');
    }

    public function requireReady(): void
    {
        abort_unless($this->ready(), 503, 'Demo access is not available.');
    }
}
