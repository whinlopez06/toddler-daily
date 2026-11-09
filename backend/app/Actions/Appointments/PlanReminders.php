<?php

namespace App\Actions\Appointments;

use App\Models\Appointment;
use App\Models\ReminderDelivery;

class PlanReminders
{
    public function handle(Appointment $appointment): void
    {
        if ($appointment->child->family->owner->demoAccount) {
            $appointment->deliveries()->whereIn('status', ['pending', 'processing'])->update(['status' => 'cancelled']);
            return;
        }
        ReminderDelivery::where('appointment_id', $appointment->id)->where('schedule_version', '<>', $appointment->schedule_version)
            ->whereIn('status', ['pending','processing'])->update(['status' => 'cancelled', 'updated_at' => now()]);
        if ($appointment->status !== 'scheduled' || !$appointment->reminders_enabled || $appointment->child->archived_at || $appointment->child->family->deletion_requested_at) {
            $appointment->deliveries()->whereIn('status', ['pending','processing'])->update(['status' => 'cancelled']);
            return;
        }
        $owner = $appointment->child->family->owner;
        foreach ([1440, 60] as $minutes) {
            $due = $appointment->starts_at->subMinutes($minutes);
            if ($due->isPast()) continue;
            foreach (['in_app', 'email'] as $channel) {
                if ($channel === 'email' && (!$owner->email_reminders_enabled || !config('tracker.reminder_email_enabled'))) continue;
                ReminderDelivery::firstOrCreate([
                    'appointment_id' => $appointment->id, 'schedule_version' => $appointment->schedule_version,
                    'user_id' => $owner->id, 'lead_minutes' => $minutes, 'channel' => $channel,
                ], ['due_at' => $due, 'expires_at' => $due->addHour()->min($appointment->starts_at)]);
            }
        }
    }
}
