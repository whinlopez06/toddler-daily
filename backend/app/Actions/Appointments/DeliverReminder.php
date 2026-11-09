<?php

namespace App\Actions\Appointments;

use App\Models\{Appointment, Child, Family, InAppNotification, ReminderDelivery};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

class DeliverReminder
{
    public function handle(ReminderDelivery $claimed): string
    {
        return DB::transaction(function () use ($claimed) {
            $family = Family::where('owner_user_id', $claimed->user_id)->lockForUpdate()->first();
            $appointment = Appointment::find($claimed->appointment_id);
            $child = $appointment ? Child::whereKey($appointment->child_id)->lockForUpdate()->first() : null;
            $appointment = $appointment ? Appointment::whereKey($appointment->id)->lockForUpdate()->first() : null;
            $delivery = ReminderDelivery::whereKey($claimed->id)->lockForUpdate()->firstOrFail();
            if ($delivery->status !== 'processing') return $delivery->status;
            if ($delivery->user->demoAccount) {
                $delivery->update(['status' => 'cancelled', 'locked_until' => null]);
                return 'cancelled';
            }
            if ($delivery->expires_at->isPast()) {
                $delivery->update(['status' => 'expired', 'locked_until' => null]);
                return 'expired';
            }
            if (!$family || $family->deletion_requested_at || !$child || $child->family_id !== $family->id || $child->archived_at ||
                !$appointment || $appointment->status !== 'scheduled' || !$appointment->reminders_enabled ||
                $appointment->schedule_version !== $delivery->schedule_version || $appointment->starts_at->isPast()) {
                $delivery->update(['status' => 'cancelled', 'locked_until' => null]);
                return 'cancelled';
            }
            if ($delivery->channel === 'in_app') {
                InAppNotification::firstOrCreate(['reminder_delivery_id' => $delivery->id], [
                    'user_id' => $delivery->user_id, 'type' => 'appointment_reminder',
                    'data' => ['appointment_id' => $appointment->id, 'child_id' => $child->id, 'message' => 'You have an upcoming appointment.'],
                ]);
                $messageId = null;
            } else {
                $owner = $delivery->user;
                if (!$owner->email_reminders_enabled || !config('tracker.reminder_email_enabled')) {
                    $delivery->update(['status' => 'cancelled', 'locked_until' => null]);
                    return 'cancelled';
                }
                $link = rtrim(config('tracker.frontend_url'), '/').'/children/'.$child->id.'/appointments';
                $body = 'You have an upcoming appointment. Sign in to view its details: '.$link.'. Reminders are best effort.';
                if (config('tracker.resend_api_key')) {
                    $response = Http::withToken(config('tracker.resend_api_key'))->withHeaders(['Idempotency-Key' => 'appointment-reminder/'.$delivery->id])
                        ->timeout(3)->post('https://api.resend.com/emails', [
                            'from' => config('mail.from.name').' <'.config('mail.from.address').'>', 'to' => [$owner->email],
                            'subject' => 'Upcoming appointment', 'text' => $body,
                        ]);
                    if (!$response->successful()) throw new RuntimeException('provider_rejected');
                    $messageId = $response->json('id');
                } else {
                    Mail::raw($body, function ($message) use ($owner, $delivery) {
                        $message->to($owner->email)->subject('Upcoming appointment');
                        $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $delivery->id.'@reminders.toddler-daily.local');
                    });
                    $messageId = 'smtp-accepted';
                }
            }
            $delivery->update(['status' => 'accepted', 'provider_message_id' => $messageId, 'locked_until' => null, 'last_error_code' => null]);
            return 'accepted';
        });
    }
}
