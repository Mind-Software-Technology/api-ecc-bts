<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\GoogleIdTokenVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeVerifier(array|false $payload): void
    {
        $verifier = new class($payload) extends GoogleIdTokenVerifier
        {
            public function __construct(private array|false $payload) {}

            public function verify(string $idToken): array|false
            {
                return $this->payload;
            }
        };

        $this->app->instance(GoogleIdTokenVerifier::class, $verifier);
    }

    public function test_new_user_is_created_and_logged_in_via_google(): void
    {
        $this->fakeVerifier(['email' => 'gita@example.com', 'name' => 'Gita', 'email_verified' => true]);

        $response = $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/google', ['id_token' => 'fake-token']);

        $response->assertCreated();
        $response->assertJsonPath('email', 'gita@example.com');

        $user = User::where('email', 'gita@example.com')->firstOrFail();
        $this->assertTrue($user->hasVerifiedEmail());
    }

    public function test_existing_user_logs_in_via_google_without_duplicating(): void
    {
        $existing = User::factory()->create(['email' => 'budi@example.com']);
        $this->fakeVerifier(['email' => 'budi@example.com', 'name' => 'Budi', 'email_verified' => true]);

        $response = $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/google', ['id_token' => 'fake-token']);

        $response->assertOk();
        $response->assertJsonPath('id', $existing->id);
        $this->assertSame(1, User::where('email', 'budi@example.com')->count());
    }

    public function test_invalid_google_token_is_rejected(): void
    {
        $this->fakeVerifier(false);

        $response = $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/google', ['id_token' => 'bad-token']);

        $response->assertStatus(401);
    }

    public function test_unverified_google_email_is_rejected(): void
    {
        $this->fakeVerifier(['email' => 'x@example.com', 'name' => 'X', 'email_verified' => false]);

        $response = $this->withHeader('Referer', 'http://localhost')
            ->postJson('/api/auth/google', ['id_token' => 'fake-token']);

        $response->assertStatus(401);
        $this->assertSame(0, User::where('email', 'x@example.com')->count());
    }
}
