<?php

return [
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost:4300')),
    'maintenance_secret' => env('MAINTENANCE_SECRET'),
    'reminder_email_enabled' => env('REMINDER_EMAIL_ENABLED', false),
    'privacy_notice_version' => env('PRIVACY_NOTICE_VERSION', '2026-11-01'),
    'resend_api_key' => env('RESEND_API_KEY'),
    'supabase_url' => env('SUPABASE_URL'),
    'supabase_storage_key' => env('SUPABASE_STORAGE_KEY'),
];
