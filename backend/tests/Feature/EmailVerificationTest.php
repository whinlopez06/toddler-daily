<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verification_preserves_signed_host_and_rejects_tampering(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        URL::forceRootUrl('http://localhost:4300');
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->id, 'hash' => sha1($user->email),
        ]);
        $this->actingAs($user);
        $this->get(str_replace('http://localhost:4300', 'http://127.0.0.1:8000', $url))->assertForbidden();
        $this->get($url.'&tampered=1')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
