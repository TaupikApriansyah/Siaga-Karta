<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_identity_is_case_insensitive_and_persisted_normalized(): void
    {
        $user = User::create([
            'name' => 'Kota Test',
            'username' => 'AdMiN.Test',
            'email' => 'ADMIN.TEST@EXAMPLE.COM',
            'password' => Hash::make('StrongPass123!'),
            'role' => 'kota',
            'is_active' => true,
        ]);

        $this->assertSame('admin.test', $user->fresh()->username);
        $this->assertSame('admin.test@example.com', $user->fresh()->email);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
            ->postJson('/api/auth/login', [
                'login' => '  ADMIN.TEST  ',
                'password' => 'StrongPass123!',
            ]);

        $response->assertOk()
            ->assertJsonPath('user.role', 'kota')
            ->assertJsonStructure(['token', 'expires_at', 'absolute_expires_at']);
    }

    public function test_failed_login_is_audited_without_exposing_internal_reason_to_client(): void
    {
        User::create([
            'name' => 'Kelurahan Test',
            'username' => 'petugas.test',
            'email' => 'petugas.test@example.com',
            'password' => Hash::make('StrongPass123!'),
            'role' => 'kelurahan',
            'is_active' => true,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.41'])
            ->postJson('/api/auth/login', [
                'login' => 'petugas.test',
                'password' => 'WrongPass123!',
            ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Email/username atau password salah.']);

        $audit = AuditLog::where('action', 'auth.login_failed')->latest('id')->firstOrFail();
        $this->assertSame('bad_password', $audit->metadata['reason']);
    }

    public function test_login_rate_limit_blocks_repeated_attempts_for_same_identity_and_ip(): void
    {
        User::create([
            'name' => 'Rate Test',
            'username' => 'rate.test',
            'email' => 'rate.test@example.com',
            'password' => Hash::make('StrongPass123!'),
            'role' => 'kelurahan',
            'is_active' => true,
        ]);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.42'])
                ->postJson('/api/auth/login', [
                    'login' => 'rate.test',
                    'password' => 'WrongPass123!',
                ])
                ->assertStatus(422);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.42'])
            ->postJson('/api/auth/login', [
                'login' => 'rate.test',
                'password' => 'WrongPass123!',
            ])
            ->assertStatus(429)
            ->assertHeader('Retry-After');

        $this->assertTrue(
            AuditLog::where('action', 'auth.login_failed')
                ->whereJsonContains('metadata->reason', 'rate_limited')
                ->exists()
        );
    }
}
