<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Hash, Notification};
use Tests\TestCase;

class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_recovery_works_after_logout_and_reset_tokens_are_single_use(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/auth/logout')->assertNoContent();
        // The browser is already a guest when it opens the mailed reset link.
        // Logout is authenticated in Fortify and must be tolerated by the client.
        $this->postJson('/auth/logout')->assertUnauthorized();
        $this->postJson('/auth/forgot-password', ['email' => $user->email])->assertOk();
        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;
            return true;
        });
        $this->assertNotNull($token);
        $this->postJson('/auth/logout')->assertUnauthorized();
        $data = ['email' => $user->email, 'token' => $token, 'password' => 'new-synthetic-password', 'password_confirmation' => 'new-synthetic-password'];
        $this->postJson('/auth/reset-password', [...$data, 'token' => 'invalid'])->assertUnprocessable();
        $this->postJson('/auth/reset-password', [...$data, 'password_confirmation' => 'mismatch'])->assertUnprocessable();
        $this->postJson('/auth/reset-password', $data)->assertOk()->assertJsonPath('password_reset', true);
        $this->assertTrue(Hash::check($data['password'], $user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->postJson('/auth/reset-password', $data)->assertUnprocessable();
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/auth/login', ['email' => $user->email, 'password' => $data['password']])->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_authenticated_recovery_redirects_until_the_session_is_ended(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/auth/forgot-password', ['email' => $user->email])->assertRedirect();
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }
}
