<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_karta_can_access_finance_but_not_citizen_operations(): void
    {
        $user = User::create([
            'name' => 'Karta Test',
            'username' => 'karta.test',
            'email' => 'karta.test@example.com',
            'password' => Hash::make('StrongPass123!'),
            'role' => 'karta',
            'is_active' => true,
        ]);

        $plain = 'test-token-for-karta';
        ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
            'absolute_expires_at' => now()->addHours(2),
            'last_used_at' => now(),
        ]);

        $headers = ['Authorization' => 'Bearer '.$plain];

        $this->withHeaders($headers)->getJson('/api/transactions')->assertOk();
        $this->withHeaders($headers)->getJson('/api/reports')->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/users')->assertForbidden();
    }
}
