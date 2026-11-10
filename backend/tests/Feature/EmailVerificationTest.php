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

    public function test_hosted_verification_validates_the_public_url_through_the_proxy(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $publicOrigin = 'https://weegrow.example.test';
        $backendOrigin = 'http://render.example.test';
        config(['tracker.frontend_url' => $publicOrigin]);
        URL::forceRootUrl($publicOrigin);
        URL::forceScheme('https');
        $parameters = ['id' => $user->id, 'hash' => sha1($user->email)];
        $valid = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), $parameters);
        $expired = URL::temporarySignedRoute('verification.verify', now()->subMinute(), $parameters);
        $throughProxy = fn (string $url): string => str_replace($publicOrigin, $backendOrigin, $url);

        $this->actingAs($user);
        $this->get($throughProxy($expired))->assertForbidden();
        $this->get($throughProxy($valid).'&tampered=1')->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->get($throughProxy($valid))->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }
}
