<?php

namespace Tests\Feature;

use App\Actions\Appointments\PlanReminders;
use App\Actions\Privacy\PurgeFamily;
use App\Actions\ProcessReminders;
use App\Models\{Appointment, Child, DeletionRequest, Family, GrowthMeasurement, InAppNotification, Medication, MedicationIntake, ReminderDelivery, TimelineEntry, User};
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http, Mail, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_life_events_can_be_created_updated_and_filtered(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2024-09-02T00:00:00Z'));
        $owner = $this->owner();
        $child = $this->child($owner);
        $path = '/api/v1/children/'.$child->id.'/timeline-entries';
        $data = ['kind' => 'life_event', 'title' => 'First day at school', 'entry_date' => '2024-09-01'];
        $response = $this->actingAs($owner)->postJson($path, $data)->assertCreated()->assertJsonPath('data.kind', 'life_event');
        $this->patchJson($path.'/'.$response->json('data.id'), [...$data, 'version' => 1, 'title' => 'School life event'])
            ->assertOk()->assertJsonPath('data.title', 'School life event');
        $this->getJson('/api/v1/timeline?kind=life_event')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/dashboard')->assertOk()->assertJsonPath('data.recent_entries.0.kind', 'life_event');
    }

    public function test_dashboard_recent_entries_use_the_family_calendar_year(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2025-12-31T17:00:00Z'));
        $owner = $this->owner();
        $child = $this->child($owner);
        $previousYear = TimelineEntry::create([
            'child_id' => $child->id, 'created_by' => $owner->id, 'kind' => 'memory',
            'title' => 'New Year’s Eve', 'entry_date' => '2025-12-31',
        ]);
        $currentYear = TimelineEntry::create([
            'child_id' => $child->id, 'created_by' => $owner->id, 'kind' => 'milestone',
            'title' => 'New Year’s Day', 'entry_date' => '2026-01-01',
        ]);

        $this->actingAs($owner)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonCount(1, 'data.recent_entries')
            ->assertJsonPath('data.recent_entries.0.id', $currentYear->id);
        $this->getJson('/api/v1/timeline')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', $previousYear->id);
    }

    public function test_dashboard_medications_follow_family_dates_and_child_scope(): void
    {
        // UTC is still November 3, but the family's local date is November 4.
        $this->travelTo(\Carbon\Carbon::parse('2026-11-03T17:00:00Z'));
        $owner = $this->owner();
        $child = $this->child($owner);
        $sibling = $this->child($owner);
        $other = $this->owner();
        $create = function (User $user, Child $profile, ?string $start, ?string $end, bool $archived = false) {
            $medication = $this->medication($user, $profile);
            $medication->update(['start_date' => $start, 'end_date' => $end, 'archived_at' => $archived ? now() : null]);
            return $medication;
        };
        $boundary = $create($owner, $child, '2026-11-04', '2026-11-04');
        $ongoing = $create($owner, $child, '2026-11-01', null);
        $siblingMedication = $create($owner, $sibling, '2026-11-01', '2026-11-10');
        $create($owner, $child, '2026-11-05', null);
        $create($owner, $child, '2026-11-01', '2026-11-03');
        $create($owner, $child, null, null);
        $create($owner, $child, '2026-11-01', null, true);
        $create($other, $this->child($other), '2026-11-01', null);
        $archived = $this->child($owner);
        $archived->update(['archived_at' => now()]);
        $create($owner, $archived, '2026-11-01', null);
        $response = $this->actingAs($owner)->getJson('/api/v1/dashboard')->assertOk();
        $this->assertEqualsCanonicalizing([$boundary->id, $ongoing->id, $siblingMedication->id], array_column($response->json('data.medications'), 'id'));
        $response = $this->getJson('/api/v1/dashboard?child_id='.$child->id)->assertOk();
        $this->assertEqualsCanonicalizing([$boundary->id, $ongoing->id], array_column($response->json('data.medications'), 'id'));
        $this->getJson('/api/v1/dashboard?child_id='.$this->child($other)->id)->assertNotFound();
        $this->travelBack();
    }

    public function test_child_name_parts_and_gender_are_saved_and_validated(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner);
        $data = ['first_name'=>' Sample ', 'middle_name'=>'Middle', 'last_name'=>'Child', 'suffix'=>'Jr.', 'gender'=>'male', 'birth_date'=>'2024-03-15'];
        $response = $this->postJson('/api/v1/children', $data)->assertCreated()
            ->assertJsonPath('data.display_name', 'Sample Middle Child Jr.')
            ->assertJsonPath('data.gender', 'male')->assertJsonPath('data.first_name', 'Sample');
        $id = $response->json('data.id');
        $this->getJson('/api/v1/children/'.$id)->assertOk()->assertJsonPath('data.suffix', 'Jr.');
        $this->patchJson('/api/v1/children/'.$id, [...$data, 'middle_name'=>'', 'suffix'=>'', 'gender'=>'female', 'version'=>1])
            ->assertOk()->assertJsonPath('data.display_name', 'Sample Child')->assertJsonPath('data.middle_name', null)->assertJsonPath('data.gender', 'female');
        $this->postJson('/api/v1/children', [...$data, 'gender'=>'invalid'])->assertUnprocessable()->assertJsonValidationErrors('gender');
        $this->postJson('/api/v1/children', [...$data, 'last_name'=>''])->assertUnprocessable()->assertJsonValidationErrors('last_name');
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        Family::create(['owner_user_id' => $user->id, 'name' => 'Sample family', 'timezone' => 'Asia/Manila']);
        return $user->fresh();
    }

    private function child(User $owner, string $name = 'Sample child'): Child
    {
        return $owner->family->children()->create(['display_name' => $name, 'birth_date' => '2024-03-15']);
    }

    private function medication(User $owner, Child $child): Medication
    {
        return Medication::create(['child_id' => $child->id, 'created_by' => $owner->id, 'name' => 'Parent supplied medicine name', 'instructions' => 'Private clinician instructions', 'instructed_amount' => '1.000', 'dose_unit' => 'mL']);
    }

    private function intakePayload(): array
    {
        return ['administered_at' => now()->subHour()->toIso8601String(), 'timezone' => 'Asia/Manila', 'actual_amount' => '1.000', 'dose_unit' => 'mL', 'notes' => null, 'state' => 'recorded', 'client_request_id' => (string) Str::uuid()];
    }

    public function test_private_schema_and_unauthenticated_access(): void
    {
        $this->assertSame('app', DB::selectOne('select current_schema() as name')->name);
        $this->getJson('/api/v1/children')->assertUnauthorized()->assertJsonPath('code', 'UNAUTHENTICATED')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_registration_creates_a_private_family_without_an_invitation(): void
    {
        Mail::fake();
        $data = ['name' => 'Sample parent', 'email' => 'pilot@example.test', 'password' => 'a-long-synthetic-password', 'password_confirmation' => 'a-long-synthetic-password', 'family_name' => 'Sample family', 'timezone' => 'Asia/Manila', 'privacy_accepted' => true];
        $this->postJson('/auth/register', $data)->assertCreated();
        $this->assertDatabaseCount('families', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('pilot_invitations', 0);
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertSame($user->id, Family::firstOrFail()->owner_user_id);
        $this->assertNull($user->email_verified_at);
        $this->assertNotNull($user->privacy_accepted_at);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($data['password'], $user->password));
        $this->getJson('/api/v1/children')->assertForbidden();
    }

    public function test_signup_still_requires_valid_details_and_rejects_duplicate_email(): void
    {
        Mail::fake();
        $data = ['name' => 'Sample parent', 'email' => 'sample@example.test', 'password' => 'a-long-synthetic-password', 'password_confirmation' => 'a-long-synthetic-password', 'family_name' => 'Sample family', 'timezone' => 'Asia/Manila', 'privacy_accepted' => true];
        $this->postJson('/auth/register', [...$data, 'password' => 'short', 'privacy_accepted' => false, 'timezone' => 'invalid'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password', 'privacy_accepted', 'timezone']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('families', 0);
        User::factory()->create(['email' => $data['email']]);
        $this->postJson('/auth/register', $data)->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('families', 0);
    }

    public function test_family_isolation_covers_nested_records_dashboard_and_export(): void
    {
        $owner = $this->owner(); $other = $this->owner();
        $child = $this->child($owner); $foreign = $this->child($other, 'Other family child');
        $entry = TimelineEntry::create(['child_id' => $foreign->id, 'created_by' => $other->id, 'kind' => 'health_note', 'title' => 'Private foreign history', 'entry_date' => '2026-01-01', 'description' => 'Private foreign note']);
        $this->actingAs($owner);
        $this->getJson('/api/v1/children/'.$foreign->id)->assertNotFound();
        $this->getJson('/api/v1/children/'.$child->id.'/timeline-entries/'.$entry->id)->assertNotFound();
        $this->getJson('/api/v1/dashboard')->assertOk()->assertDontSee('Other family child')->assertDontSee('Private foreign');
        $this->getJson('/api/v1/timeline')->assertOk()->assertJsonCount(0, 'data');
        $this->withSession(['auth.password_confirmed_at' => time()])->get('/api/v1/family/export')->assertOk();
        $response = $this->withSession(['auth.password_confirmed_at' => time()])->get('/api/v1/family/export');
        // Exports are throttled separately; a second export cannot bypass the limit.
        $response->assertStatus(429);
    }

    public function test_child_version_checks_and_calendar_date_validation(): void
    {
        $owner = $this->owner(); $child = $this->child($owner); $this->actingAs($owner);
        $path = '/api/v1/children/'.$child->id;
        $payload = ['display_name' => 'Updated sample child', 'birth_date' => '2024-03-15', 'version' => 1];
        $this->patchJson($path, $payload)->assertOk()->assertJsonPath('data.version', 2);
        $this->patchJson($path, $payload)->assertConflict();
        $this->postJson('/api/v1/children', ['display_name' => 'Future date', 'birth_date' => now()->addDays(3)->toDateString()])->assertUnprocessable();
    }

    public function test_growth_validation_and_postgresql_constraints(): void
    {
        $owner = $this->owner(); $child = $this->child($owner); $this->actingAs($owner);
        $path = '/api/v1/children/'.$child->id.'/growth-measurements';
        $this->postJson($path, ['measured_on' => '2026-01-01'])->assertUnprocessable();
        $this->postJson($path, ['measured_on' => '2026-01-01', 'weight_value' => '-1', 'weight_unit' => 'kg'])->assertUnprocessable();
        $this->postJson($path, ['measured_on' => '2026-01-01', 'weight_value' => '10.125', 'weight_unit' => 'kg'])->assertCreated()->assertJsonPath('data.weight_value', '10.125');
        try {
            DB::transaction(fn () => GrowthMeasurement::create(['child_id' => $child->id, 'created_by' => $owner->id, 'measured_on' => '2026-01-01', 'weight_value' => 10]));
            $this->fail('PostgreSQL accepted a quantity without a unit.');
        } catch (QueryException) { $this->assertDatabaseCount('growth_measurements', 1); }
    }

    public function test_medication_idempotency_snapshot_and_append_only_corrections(): void
    {
        $owner = $this->owner(); $child = $this->child($owner); $med = $this->medication($owner, $child); $this->actingAs($owner);
        $path = '/api/v1/children/'.$child->id.'/medications/'.$med->id.'/intakes';
        $payload = $this->intakePayload();
        $first = $this->postJson($path, $payload)->assertCreated()->json('data.id');
        $this->postJson($path, $payload)->assertCreated()->assertJsonPath('data.id', $first);
        $this->postJson($path, [...$payload, 'actual_amount' => '2.000'])->assertConflict();
        $med->update(['instructions' => 'Changed later']);
        $correction = [...$payload, 'client_request_id' => (string) Str::uuid(), 'actual_amount' => '2.000', 'correction_reason' => 'Corrected a transcription error'];
        $this->postJson($path.'/'.$first.'/corrections', $correction)->assertCreated()->assertJsonPath('data.snapshot.instructions', 'Private clinician instructions');
        $this->assertSame('1.000', MedicationIntake::findOrFail($first)->actual_amount);
        $this->assertDatabaseCount('medication_intakes', 2);
        $this->postJson($path.'/'.$first.'/corrections', [...$correction, 'client_request_id' => (string) Str::uuid()])->assertConflict();
        $this->assertStringNotContainsString('Private clinician instructions', DB::table('medication_intakes')->where('id', $first)->value('snapshot'));
        try {
            DB::transaction(fn () => DB::table('medication_intakes')->where('id', $first)->update(['actual_amount' => 4]));
            $this->fail('The database allowed an intake mutation.');
        } catch (QueryException) { $this->assertSame('1.000', MedicationIntake::findOrFail($first)->actual_amount); }
    }

    public function test_unknown_actual_dose_is_not_inferred(): void
    {
        $owner = $this->owner(); $child = $this->child($owner); $med = $this->medication($owner, $child); $this->actingAs($owner);
        $this->postJson('/api/v1/children/'.$child->id.'/medications/'.$med->id.'/intakes', [...$this->intakePayload(), 'actual_amount' => null, 'dose_unit' => null])->assertCreated()->assertJsonPath('data.actual_amount', null);
    }

    public function test_profile_and_record_deletion_respects_history_and_removes_reminders(): void
    {
        $owner = $this->owner();
        $child = $this->child($owner);
        $appointment = $this->appointment($owner, $child);
        $path = '/api/v1/children/'.$child->id;
        $visit = $path.'/appointments/'.$appointment->id;
        $version = ['version' => 1];
        $this->actingAs($this->owner())->deleteJson($visit, $version)->assertNotFound();
        $this->deleteJson($path, $version)->assertNotFound();
        $this->actingAs($owner)->deleteJson($path, $version)->assertConflict();
        $this->deleteJson($visit, ['version' => 2])->assertConflict();
        $this->deleteJson($visit, $version)->assertNoContent();
        $this->assertDatabaseMissing('reminder_deliveries', ['appointment_id' => $appointment->id]);
        $medication = $this->medication($owner, $child);
        $this->deleteJson($path.'/medications/'.$medication->id, $version)->assertNoContent();
        $this->deleteJson($path, $version)->assertNoContent();
        $this->getJson($path)->assertNotFound();

        $child = $this->child($owner);
        $medication = $this->medication($owner, $child);
        $path = '/api/v1/children/'.$child->id.'/medications/'.$medication->id;
        $this->postJson($path.'/intakes', $this->intakePayload())->assertCreated();
        $this->deleteJson($path, $version)->assertConflict();
        $this->assertDatabaseHas('medication_intakes', ['medication_id' => $medication->id]);
    }

    private function appointment(User $owner, Child $child): Appointment
    {
        $appointment = Appointment::create(['child_id' => $child->id, 'created_by' => $owner->id, 'title' => 'Sample follow-up', 'starts_at' => now()->addDays(2)->startOfMinute(), 'timezone' => 'Asia/Manila', 'status' => 'scheduled', 'reminders_enabled' => true]);
        app(PlanReminders::class)->handle($appointment);
        return $appointment;
    }

    public function test_reminder_repetition_expired_claims_and_cancellation(): void
    {
        $this->travelTo(now()->startOfMinute());
        $owner = $this->owner(); $child = $this->child($owner); $appointment = $this->appointment($owner, $child);
        $this->assertDatabaseCount('reminder_deliveries', 2);
        $this->travel(1)->days();
        app(ProcessReminders::class)->handle();
        app(ProcessReminders::class)->handle();
        $this->assertDatabaseCount('notifications', 1);
        $hour = ReminderDelivery::where('lead_minutes', 60)->first();
        $this->travelTo($hour->due_at->addMinute());
        $hour->update(['status' => 'processing', 'locked_until' => now()->subMinute()]);
        app(ProcessReminders::class)->handle();
        $this->assertDatabaseCount('notifications', 2);

        $another = $this->appointment($owner, $child);
        $another->update(['status' => 'cancelled']);
        $this->travel(1)->days();
        app(ProcessReminders::class)->handle();
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_rescheduling_cancels_old_deliveries(): void
    {
        $owner = $this->owner(); $child = $this->child($owner); $appointment = $this->appointment($owner, $child);
        $this->actingAs($owner)->patchJson('/api/v1/children/'.$child->id.'/appointments/'.$appointment->id, [
            'title' => 'Sample follow-up', 'kind' => 'general', 'starts_at' => now()->addDays(4)->toIso8601String(),
            'timezone' => 'Asia/Manila', 'status' => 'scheduled', 'reminders_enabled' => true, 'version' => 1,
        ])->assertOk();
        $this->assertSame(2, ReminderDelivery::where('schedule_version', 1)->where('status', 'cancelled')->count());
        $this->assertSame(2, ReminderDelivery::where('schedule_version', 2)->where('status', 'pending')->count());
    }

    public function test_email_failure_retries_with_stable_provider_idempotency(): void
    {
        config(['tracker.reminder_email_enabled' => true, 'tracker.resend_api_key' => 'synthetic-test-key']);
        Http::fake(['api.resend.com/*' => Http::sequence()->push([], 503)->push(['id' => 'sample-provider-message'], 200)]);
        $owner = $this->owner(); $owner->update(['email_reminders_enabled' => true]); $owner->refresh();
        $child = $this->child($owner); $this->appointment($owner, $child);
        $this->travel(1)->days();
        app(ProcessReminders::class)->handle();
        $delivery = ReminderDelivery::where('channel','email')->where('lead_minutes',1440)->firstOrFail();
        $this->assertSame('pending', $delivery->status); $this->assertSame(1, $delivery->attempts);
        $this->travel(61)->seconds();
        app(ProcessReminders::class)->handle();
        $this->assertSame('accepted', $delivery->fresh()->status);
        Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', 'appointment-reminder/'.$delivery->id) && !str_contains($request->body(), 'Sample child'));
    }

    public function test_scheduler_and_notification_access_are_scoped(): void
    {
        config(['tracker.maintenance_secret' => str_repeat('s', 40)]);
        $this->postJson('/internal/maintenance/tick')->assertForbidden();
        $this->withHeader('Authorization', 'Bearer '.str_repeat('s', 40))->postJson('/internal/maintenance/tick', ['family_id' => 'anything'])->assertUnprocessable();
        $this->withHeader('Authorization', 'Bearer '.str_repeat('s', 40))->postJson('/internal/maintenance/tick')->assertOk();
        $owner = $this->owner(); $other = $this->owner(); $child = $this->child($other); $this->appointment($other, $child);
        $this->travel(1)->days(); app(ProcessReminders::class)->handle();
        $notification = InAppNotification::firstOrFail();
        $this->actingAs($owner)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
        $this->patchJson('/api/v1/notifications/'.$notification->id, ['read' => true])->assertNotFound();
    }

    public function test_export_includes_history_and_requires_password_confirmation(): void
    {
        $owner = $this->owner(); $child = $this->child($owner); $med = $this->medication($owner, $child); $this->actingAs($owner);
        $this->getJson('/api/v1/family/export')->assertStatus(423);
        $this->postJson('/api/v1/children/'.$child->id.'/medications/'.$med->id.'/intakes', $this->intakePayload())->assertCreated();
        $response = $this->withSession(['auth.password_confirmed_at' => time()])->get('/api/v1/family/export')->assertOk();
        $data = json_decode($response->streamedContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertCount(1, $data['children']); $this->assertCount(1, $data['medication_intakes']);
        $this->assertSame('Private clinician instructions', $data['medication_intakes'][0]['snapshot']['instructions']);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function test_erasure_preserves_a_separate_encrypted_deletion_ledger(): void
    {
        Storage::fake('local');
        $owner = $this->owner(); $child = $this->child($owner); $med = $this->medication($owner, $child); $this->actingAs($owner);
        $this->postJson('/api/v1/children/'.$child->id.'/medications/'.$med->id.'/intakes', $this->intakePayload())->assertCreated();
        $this->withSession(['auth.password_confirmed_at' => time()])->deleteJson('/api/v1/family', ['family_name' => 'Sample family', 'confirm' => true])->assertAccepted();
        $request = DeletionRequest::firstOrFail();
        app(PurgeFamily::class)->handle($request);
        $this->assertDatabaseCount('users', 0); $this->assertDatabaseCount('children', 0); $this->assertDatabaseCount('medication_intakes', 0);
        $this->assertSame('completed', $request->fresh()->status);
        Storage::disk('local')->assertExists('deletion-ledger/'.$request->family_id.'.enc');
        $this->assertStringNotContainsString($request->family_id, Storage::disk('local')->get('deletion-ledger/'.$request->family_id.'.enc'));
    }
}
