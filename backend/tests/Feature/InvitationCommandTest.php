<?php

namespace Tests\Feature;

use App\Models\PilotInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_issue_and_replace_an_expiring_invitation(): void
    {
        $this->artisan('tracker:invite', ['email' => 'SAMPLE@example.test', '--hours' => 24])
            ->expectsOutputToContain('/register?email=sample%40example.test&token=')->assertSuccessful();
        $first = PilotInvitation::firstOrFail();
        $this->assertSame('sample@example.test', $first->email);
        $this->assertSame(64, strlen($first->token_hash));
        $this->assertTrue($first->expires_at->isFuture());
        $this->artisan('tracker:invite', ['email' => 'sample@example.test'])->assertSuccessful();
        $this->assertDatabaseCount('pilot_invitations', 1);
        $this->assertNotSame($first->token_hash, PilotInvitation::firstOrFail()->token_hash);
    }

    public function test_invalid_email_or_lifetime_does_not_issue_an_invitation(): void
    {
        $this->artisan('tracker:invite', ['email' => 'invalid'])->assertFailed();
        $this->artisan('tracker:invite', ['email' => 'sample@example.test', '--hours' => 999])->assertFailed();
        $this->assertDatabaseCount('pilot_invitations', 0);
    }
}
