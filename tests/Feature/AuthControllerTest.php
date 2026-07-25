<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_without_frontend_origin_returns_400_instead_of_crashing(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Budi',
            'email' => 'budi@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(400);
    }

    public function test_login_without_frontend_origin_returns_400_instead_of_crashing(): void
    {
        $user = User::factory()->create(['password' => 'password123']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(400);
    }
}
