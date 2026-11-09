<?php

return [
    'enabled' => (bool) env('DEMO_MODE', false),
    'daily_accounts' => 30,
    'total_accounts' => 100,
    'events_per_account' => 200,
    'children' => 3,
    'records' => 100,
];
