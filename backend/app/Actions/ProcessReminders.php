<?php

namespace App\Actions;

use App\Actions\Appointments\DeliverReminder;
use App\Models\ReminderDelivery;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessReminders
{
    public function handle(?string $userId = null, ?float $deadline = null): array
    {
        $deadline ??= microtime(true) + 20;
        $counts = ['processed' => 0, 'accepted' => 0, 'failed' => 0];
        $emailCount = 0;
        for ($i = 0; $i < 30 && microtime(true) < $deadline - 4; $i++) {
            $delivery = DB::transaction(function () use ($userId, $emailCount) {
                $q = ReminderDelivery::where('due_at', '<=', now())->where(function ($q) {
                    $q->where(function ($q) { $q->where('status', 'pending')->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now())); })
                        ->orWhere(fn ($q) => $q->where('status', 'processing')->where('locked_until', '<=', now()));
                });
                if ($userId) $q->where('user_id', $userId)->where('channel', 'in_app');
                if ($emailCount >= 5) $q->where('channel', 'in_app');
                $d = $q->orderBy('due_at')->orderBy('id')->lock('FOR UPDATE SKIP LOCKED')->first();
                if (!$d) return null;
                $d->update(['status' => 'processing', 'attempts' => $d->attempts + 1, 'locked_until' => now()->addMinutes(2)]);
                return $d;
            });
            if (!$delivery) break;
            $counts['processed']++;
            if ($delivery->channel === 'email') $emailCount++;
            try {
                $outcome = app(DeliverReminder::class)->handle($delivery);
                if ($outcome === 'accepted') $counts['accepted']++;
            } catch (Throwable $error) {
                $counts['failed']++;
                $uncertainSmtp = $delivery->channel === 'email' && !config('tracker.resend_api_key');
                $retry = !$uncertainSmtp && $delivery->attempts < 4 && $delivery->expires_at->isFuture();
                $delay = [1 => 60, 2 => 300, 3 => 900][$delivery->attempts] ?? 900;
                ReminderDelivery::whereKey($delivery->id)->where('status', 'processing')->update([
                    'status' => $retry ? 'pending' : 'failed', 'locked_until' => null,
                    'next_attempt_at' => $retry ? now()->addSeconds($delay) : null,
                    'last_error_code' => $uncertainSmtp ? 'delivery_uncertain' : 'delivery_failed', 'updated_at' => now(),
                ]);
                // Never put email content, recipient addresses, or provider responses in logs.
            }
        }
        return $counts;
    }
}
