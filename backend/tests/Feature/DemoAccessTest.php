<?php

namespace Tests\Feature;

use App\Actions\Medications\RecordIntake;
use App\Models\Appointment;
use App\Models\DemoAccount;
use App\Models\Family;
use App\Models\GrowthMeasurement;
use App\Models\InAppNotification;
use App\Models\Medication;
use App\Models\ReminderDelivery;
use App\Models\TimelineEntry;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoAccessTest extends TestCase
{
    use RefreshDatabase;

    private function enableDemo(): void
    {
        config(['demo.enabled' => true]);
    }

    private function payload(): array
    {
        return ['email' => 'visitor@example.test', 'password' => 'synthetic-demo-password',
            'password_confirmation' => 'synthetic-demo-password', 'privacy_accepted' => true];
    }

    private function owner(bool $demo): User
    {
        $user = User::factory()->create(['email_verified_at' => $demo ? null : now()]);
        Family::create(['owner_user_id' => $user->id, 'name' => 'Synthetic family', 'timezone' => 'Asia/Manila']);
        if ($demo) {
            DemoAccount::create(['user_id' => $user->id, 'started_at' => now(), 'expires_at' => now()->addDay()]);
        }

        return $user;
    }

    public function test_demo_requires_opt_in_but_allows_existing_ordinary_accounts(): void
    {
        config(['demo.enabled' => false]);
        $personal = $this->owner(false);
        $this->postJson('/auth/demo', $this->payload())->assertStatus(503);
        $this->enableDemo();
        $this->artisan('tracker:demo-init')->assertSuccessful();
        $this->assertDatabaseCount('demo_installations', 0);
        $this->postJson('/auth/demo', $this->payload())->assertCreated();
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('families', ['owner_user_id' => $personal->id]);
        $this->getJson('/auth/demo-options')->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.demo_mode', false);
    }

    public function test_signup_creates_only_its_private_sample_family_and_never_verifies_email(): void
    {
        $this->enableDemo();
        Mail::fake();
        Notification::fake();
        $this->postJson('/auth/demo', [...$this->payload(), 'email' => ' Visitor@Example.test ',
            'family_id' => 'untrusted', 'email_verified_at' => now(), 'is_admin' => true])->assertCreated();
        $user = User::firstOrFail();
        $this->assertSame('visitor@example.test', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->email_reminders_enabled);
        $this->assertTrue(Hash::check($this->payload()['password'], $user->password));
        $this->assertSame(86400, (int) $user->demoAccount->started_at->diffInSeconds($user->demoAccount->expires_at));
        $this->assertDatabaseCount('families', 1);
        $this->assertDatabaseCount('children', 1);
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.is_demo', true)->assertJsonPath('data.email_verified', false);
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonCount(1, 'data.children');
        $this->assertDatabaseHas('demo_access_events', ['user_id' => $user->id, 'event' => 'registered']);
        $this->assertDatabaseHas('demo_access_events', ['user_id' => $user->id, 'event' => 'login']);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_expiry_blocks_existing_session_and_relogin_without_deleting_history(): void
    {
        $this->enableDemo();
        $this->postJson('/auth/demo', $this->payload())->assertCreated();
        $user = User::firstOrFail();
        $expires = $user->demoAccount->expires_at;
        $this->travelTo($expires);
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'DEMO_EXPIRED');
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->postJson('/auth/login', $this->payload())->assertUnprocessable();
        $this->assertSame($expires->toIso8601String(), $user->demoAccount()->first()->expires_at->toIso8601String());
        $this->assertDatabaseCount('demo_accounts', 1);
        $this->assertDatabaseCount('children', 1);
        $this->assertSame(1, DB::table('demo_access_events')->where('event', 'expired')->count());
        $this->assertDatabaseHas('demo_access_events', ['event' => 'login']);
        $this->travelBack();
    }

    public function test_relogin_does_not_extend_expiry_and_revocation_blocks_access(): void
    {
        $this->enableDemo();
        $this->postJson('/auth/demo', $this->payload())->assertCreated();
        $user = User::firstOrFail();
        $expires = $user->demoAccount->expires_at->toIso8601String();
        $this->postJson('/auth/logout')->assertNoContent();
        $this->travel(2)->hours();
        $this->postJson('/auth/login', $this->payload())->assertOk();
        $this->assertSame($expires, $user->demoAccount()->first()->expires_at->toIso8601String());
        $this->artisan('tracker:demo-revoke', ['user' => $user->id])->assertSuccessful();
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->assertDatabaseHas('demo_access_events', ['event' => 'revoked']);
        $this->assertDatabaseCount('demo_accounts', 1);
        $this->travelBack();
    }

    public function test_demo_cannot_access_personal_family_in_the_same_database(): void
    {
        $this->enableDemo();
        $this->assertIsolation(true, false);
    }

    public function test_personal_account_cannot_access_demo_family_in_the_same_database(): void
    {
        $this->enableDemo();
        $this->assertIsolation(false, true);
    }

    public function test_demo_accounts_cannot_access_another_demo_family(): void
    {
        $this->enableDemo();
        $this->assertIsolation(true);
    }

    public function test_regular_accounts_cannot_access_another_regular_family(): void
    {
        $this->assertIsolation(false);
    }

    private function assertIsolation(bool $demo, ?bool $otherDemo = null): void
    {
        $owner = $this->owner($demo);
        $other = $this->owner($otherDemo ?? $demo);
        $own = $owner->family->children()->create(['display_name' => 'Own sample child', 'birth_date' => '2024-01-01']);
        $foreign = $other->family->children()->create(['display_name' => 'Foreign private child', 'birth_date' => '2024-01-01']);
        $entry = TimelineEntry::create(['child_id' => $foreign->id, 'created_by' => $other->id,
            'kind' => 'memory', 'title' => 'Foreign private memory', 'entry_date' => '2026-01-01']);
        $medication = Medication::create(['child_id' => $foreign->id, 'created_by' => $other->id, 'name' => 'Foreign private medicine']);
        $appointment = Appointment::create(['child_id' => $foreign->id, 'created_by' => $other->id, 'title' => 'Foreign private appointment',
            'kind' => 'general', 'starts_at' => now()->addDay(), 'timezone' => 'Asia/Manila', 'status' => 'scheduled', 'reminders_enabled' => false]);
        $growth = GrowthMeasurement::create(['child_id' => $foreign->id, 'created_by' => $other->id, 'measured_on' => '2026-01-01', 'weight_value' => 10, 'weight_unit' => 'kg']);
        $vaccination = VaccinationRecord::create(['child_id' => $foreign->id, 'created_by' => $other->id, 'vaccine_name' => 'Foreign private vaccine', 'administered_on' => '2026-01-01']);
        $foreignIntake = app(RecordIntake::class)->handle($other, $medication, [
            'administered_at' => now()->toIso8601String(), 'timezone' => 'Asia/Manila', 'state' => 'recorded', 'client_request_id' => (string) Str::uuid()]);
        $ownMedication = Medication::create(['child_id' => $own->id, 'created_by' => $owner->id, 'name' => 'Own sample medicine']);
        $delivery = ReminderDelivery::create(['appointment_id' => $appointment->id, 'schedule_version' => 1, 'user_id' => $other->id,
            'lead_minutes' => 60, 'channel' => 'in_app', 'due_at' => now(), 'expires_at' => now()->addHour()]);
        $notification = InAppNotification::create(['user_id' => $other->id, 'reminder_delivery_id' => $delivery->id, 'type' => 'sample', 'data' => ['message' => 'Foreign private notification']]);
        $this->actingAs($owner);
        foreach (['children', 'dashboard', 'timeline', 'appointments', 'notifications'] as $path) {
            $this->getJson('/api/v1/'.$path)->assertOk()->assertDontSee('Foreign private');
        }
        foreach (['dashboard', 'timeline', 'appointments'] as $path) {
            $this->getJson('/api/v1/'.$path.'?child_id='.$foreign->id)->assertNotFound();
        }
        $this->getJson('/api/v1/children/'.$foreign->id)->assertNotFound();
        $this->patchJson('/api/v1/children/'.$foreign->id, ['display_name' => 'Changed', 'birth_date' => '2024-01-01', 'version' => 1])->assertNotFound();
        $records = [
            'timeline-entries' => [$entry, ['kind' => 'memory', 'title' => 'Changed', 'entry_date' => '2026-01-01']],
            'growth-measurements' => [$growth, ['measured_on' => '2026-01-01', 'weight_value' => 12, 'weight_unit' => 'kg']],
            'vaccination-records' => [$vaccination, ['vaccine_name' => 'Changed', 'administered_on' => '2026-01-01']],
            'medications' => [$medication, ['name' => 'Changed']],
            'appointments' => [$appointment, ['title' => 'Changed', 'kind' => 'general', 'starts_at' => now()->addDay()->toIso8601String(), 'timezone' => 'Asia/Manila', 'status' => 'scheduled', 'reminders_enabled' => false]],
        ];
        foreach ($records as $type => [$record, $data]) {
            $this->getJson('/api/v1/children/'.$foreign->id.'/'.$type)->assertNotFound();
            $recordPath = '/api/v1/children/'.$own->id.'/'.$type.'/'.$record->id;
            $this->getJson($recordPath)->assertNotFound();
            $this->patchJson($recordPath, [...$data, 'version' => 1])->assertNotFound();
            if (in_array($type, ['timeline-entries', 'growth-measurements', 'vaccination-records'])) {
                $this->deleteJson($recordPath, ['version' => 1])->assertNotFound();
            }
        }
        $path = '/api/v1/children/'.$own->id.'/timeline-entries/'.$entry->id;
        $this->patchJson($path, ['kind' => 'memory', 'title' => 'Changed', 'entry_date' => '2026-01-01', 'version' => 1])->assertNotFound();
        $this->deleteJson($path, ['version' => 1])->assertNotFound();
        $this->postJson('/api/v1/children/'.$foreign->id.'/timeline-entries', ['kind' => 'memory', 'title' => 'Injected', 'entry_date' => '2026-01-01'])->assertNotFound();
        $this->getJson('/api/v1/children/'.$own->id.'/medications/'.$medication->id.'/intakes')->assertNotFound();
        $this->postJson('/api/v1/children/'.$own->id.'/medications/'.$medication->id.'/intakes', [
            'administered_at' => now()->toIso8601String(), 'timezone' => 'Asia/Manila', 'state' => 'recorded', 'client_request_id' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->patchJson('/api/v1/notifications/'.$notification->id, ['read' => true])->assertNotFound();
        $intakePath = '/api/v1/children/'.$own->id.'/medications/'.$ownMedication->id.'/intakes/'.$foreignIntake->id;
        $this->getJson($intakePath)->assertNotFound();
        $this->postJson($intakePath.'/corrections', ['administered_at' => now()->toIso8601String(), 'timezone' => 'Asia/Manila',
            'state' => 'voided', 'client_request_id' => (string) Str::uuid(), 'correction_reason' => 'Cross-family attempt'])->assertNotFound();
        $this->withSession(['auth.password_confirmed_at' => time()])->getJson('/api/v1/family/export')->assertOk()->assertDontSee('Foreign private');
        $this->assertSame('Foreign private memory', $entry->fresh()->title);
        $this->assertSame('Foreign private child', $foreign->fresh()->display_name);
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_demo_rejects_its_own_recovery_email_and_self_deletion(): void
    {
        $this->enableDemo();
        Mail::fake();
        Notification::fake();
        $demoUser = $this->owner(true);
        $this->postJson('/auth/forgot-password', ['email' => $demoUser->email])->assertOk();
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->postJson('/auth/reset-password', ['email' => $demoUser->email])->assertUnprocessable();
        $this->postJson('/auth/demo', $this->payload())->assertCreated();
        $this->postJson('/auth/email/verification-notification')->assertForbidden();
        $this->patchJson('/api/v1/me', ['email_reminders_enabled' => true])->assertForbidden();
        $this->withSession(['auth.password_confirmed_at' => time()])->deleteJson('/api/v1/family', ['confirm' => true, 'family_name' => 'My demo family'])->assertForbidden();
        $child = User::where('email', $this->payload()['email'])->firstOrFail()->family->children()->firstOrFail();
        $this->postJson('/api/v1/children/'.$child->id.'/appointments', ['title' => 'Fictional appointment',
            'kind' => 'general', 'starts_at' => now()->addDays(2)->toIso8601String(), 'timezone' => 'Asia/Manila', 'status' => 'scheduled', 'reminders_enabled' => true])->assertCreated();
        $this->assertDatabaseCount('reminder_deliveries', 0);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_demo_quotas_and_intake_idempotency_are_preserved(): void
    {
        $this->enableDemo();
        $owner = $this->owner(true);
        $this->actingAs($owner);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/children', ['display_name' => 'Sample child '.$i, 'birth_date' => '2024-01-01'])->assertCreated();
        }
        $this->postJson('/api/v1/children', ['display_name' => 'Over quota', 'birth_date' => '2024-01-01'])->assertConflict();
        $child = $owner->family->children()->firstOrFail();
        $med = Medication::create(['child_id' => $child->id, 'created_by' => $owner->id, 'name' => 'Fictional medicine']);
        $owner->demoAccount()->update(['records_created' => 99]);
        $payload = ['administered_at' => now()->toIso8601String(), 'timezone' => 'Asia/Manila', 'state' => 'recorded', 'client_request_id' => (string) Str::uuid()];
        $path = '/api/v1/children/'.$child->id.'/medications/'.$med->id.'/intakes';
        $first = $this->postJson($path, $payload)->assertCreated()->json('data.id');
        $this->postJson($path, $payload)->assertCreated()->assertJsonPath('data.id', $first);
        $this->postJson($path, [...$payload, 'client_request_id' => (string) Str::uuid()])->assertConflict();
        $this->assertSame(100, $owner->demoAccount()->first()->records_created);
        $this->assertDatabaseCount('medication_intakes', 1);
    }

    public function test_signup_rate_limit_and_global_capacity_are_enforced(): void
    {
        $this->enableDemo();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/auth/demo', [])->assertUnprocessable();
        }
        $this->postJson('/auth/demo', $this->payload())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('users', 0);
        config(['demo.daily_accounts' => 0]);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->postJson('/auth/demo', $this->payload())->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_visitor_history_has_no_http_endpoint_and_operator_report_is_bounded(): void
    {
        $this->enableDemo();
        $this->postJson('/auth/demo', $this->payload())->assertCreated();
        $this->getJson('/api/v1/demo-report')->assertNotFound();
        $this->artisan('tracker:demo-report', ['--limit' => 201])->assertFailed();
        $this->artisan('tracker:demo-report', ['--events' => true])->assertSuccessful();
        $demo = DemoAccount::firstOrFail();
        config(['demo.events_per_account' => 3]);
        $demo->recordEvent('login_failed');
        $demo->recordEvent('login_failed');
        $this->assertDatabaseCount('demo_access_events', 3);
    }

    public function test_demo_write_throttle_preserves_retry_after_and_rejects_the_write(): void
    {
        $this->enableDemo();
        $owner = $this->owner(true);
        $this->actingAs($owner);
        for ($i = 0; $i < 20; $i++) {
            $this->patchJson('/api/v1/me', ['name' => 'Demo visitor'])->assertOk();
        }
        $this->patchJson('/api/v1/me', ['name' => 'Rejected change'])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertSame('Demo visitor', $owner->fresh()->name);
    }

    public function test_normal_login_and_unverified_signup_still_work_with_demo_enabled(): void
    {
        $this->enableDemo();
        Notification::fake();
        $personal = $this->owner(false);
        $personal->update(['password' => 'synthetic-normal-password']);
        $this->postJson('/auth/login', ['email' => $personal->email, 'password' => 'synthetic-normal-password'])->assertOk();
        $this->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.is_demo', false);
        $this->postJson('/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->postJson('/auth/register', [...$this->payload(), 'name' => 'Synthetic parent', 'family_name' => 'Synthetic regular family', 'timezone' => 'Asia/Manila'])->assertCreated();
        $this->getJson('/api/v1/children')->assertForbidden();
    }

    public function test_normal_recovery_is_available_with_demo_enabled(): void
    {
        $this->enableDemo();
        Notification::fake();
        $personal = $this->owner(false);
        $this->postJson('/auth/forgot-password', ['email' => $personal->email])->assertOk();
        $this->assertDatabaseHas('password_reset_tokens', ['email' => $personal->email]);
        Notification::assertSentTo($personal, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_reminders_and_notification_email_are_restricted_per_account(): void
    {
        $this->enableDemo();
        config(['tracker.reminder_email_enabled' => true, 'tracker.resend_api_key' => 'synthetic-key']);
        \Illuminate\Support\Facades\Http::fake(['api.resend.com/*' => \Illuminate\Support\Facades\Http::response(['id' => 'synthetic-message'], 200)]);
        foreach ([false, true] as $isDemo) {
            $owner = $this->owner($isDemo);
            $owner->update(['email_reminders_enabled' => true]);
            $child = $owner->family->children()->create(['display_name' => 'Synthetic child', 'birth_date' => '2024-01-01']);
            $appointment = Appointment::create(['child_id' => $child->id, 'created_by' => $owner->id, 'title' => 'Synthetic appointment',
                'starts_at' => now()->addDays(2), 'timezone' => 'Asia/Manila', 'status' => 'scheduled', 'reminders_enabled' => true]);
            app(\App\Actions\Appointments\PlanReminders::class)->handle($appointment);
            $this->assertSame($isDemo ? 0 : 4, $appointment->deliveries()->count());
            $delivery = ReminderDelivery::firstOrCreate(['appointment_id' => $appointment->id, 'schedule_version' => 1,
                'user_id' => $owner->id, 'lead_minutes' => 60, 'channel' => 'email'], ['due_at' => now(), 'expires_at' => now()->addHour()]);
            $delivery->update(['status' => 'processing']);
            $this->assertSame($isDemo ? 'cancelled' : 'accepted', app(\App\Actions\Appointments\DeliverReminder::class)->handle($delivery));
            $decision = \Illuminate\Support\Facades\Event::until(new \Illuminate\Notifications\Events\NotificationSending(
                $owner, new \Illuminate\Auth\Notifications\VerifyEmail(), 'mail'));
            $this->assertSame($isDemo ? false : null, $decision);
        }
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_disabling_demos_preserves_normal_account_access(): void
    {
        $this->enableDemo();
        $personal = $this->owner(false);
        $demo = $this->owner(true);
        config(['demo.enabled' => false]);
        $this->actingAs($personal)->getJson('/api/v1/dashboard')->assertOk();
        $this->actingAs($demo)->getJson('/api/v1/dashboard')->assertUnauthorized();
        $this->actingAs($personal)->getJson('/api/v1/dashboard')->assertOk();
    }
}
