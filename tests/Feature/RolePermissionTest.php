<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role): string
    {
        $region = match ($role) {
            'kota' => Region::where('level','kota')->firstOrFail(),
            'kecamatan' => Region::where('code','32.73.05')->firstOrFail(),
            default => Region::where('code','32.73.05.1002')->firstOrFail(),
        };
        $user = User::create([
            'name' => 'Karang Taruna '.ucfirst($role).' Test',
            'username' => $role.'.test',
            'email' => $role.'.test@example.com',
            'password' => Hash::make('StrongPass123!'),
            'role' => $role,
            'region_id' => $region->id,
            'is_active' => true,
        ]);
        $plain = 'test-token-'.$role;
        ApiToken::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addHour(),
            'absolute_expires_at' => now()->addHours(2),
            'last_used_at' => now(),
        ]);
        return $plain;
    }

    public function test_kelurahan_can_handle_its_reports_but_cannot_access_finance_or_users(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor('kelurahan')];
        $this->withHeaders($headers)->getJson('/api/reports')->assertOk();
        $this->withHeaders($headers)->getJson('/api/transactions')->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/users')->assertForbidden();
    }

    public function test_kecamatan_has_validation_access_without_finance_or_user_management(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->tokenFor('kecamatan')];
        $this->withHeaders($headers)->getJson('/api/reports')->assertOk();
        $this->withHeaders($headers)->getJson('/api/transactions')->assertForbidden();
        $this->withHeaders($headers)->getJson('/api/users')->assertForbidden();
    }

    public function test_only_kota_can_open_city_map_endpoint(): void
    {
        $kota = ['Authorization' => 'Bearer '.$this->tokenFor('kota')];
        $this->withHeaders($kota)->getJson('/api/dashboard/kota/map')->assertOk();

        // New test database needs unique usernames, so build distinct lower users manually.
        foreach (['kecamatan','kelurahan'] as $role) {
            $region = $role==='kecamatan' ? Region::where('code','32.73.05')->firstOrFail() : Region::where('code','32.73.05.1002')->firstOrFail();
            $user=User::create([
                'name'=>'Blocked '.$role,'username'=>'blocked.'.$role,'email'=>'blocked.'.$role.'@example.com',
                'password'=>Hash::make('StrongPass123!'),'role'=>$role,'region_id'=>$region->id,'is_active'=>true,
            ]);
            $plain='blocked-token-'.$role;
            ApiToken::create(['user_id'=>$user->id,'token_hash'=>hash('sha256',$plain),'expires_at'=>now()->addHour(),'absolute_expires_at'=>now()->addHours(2),'last_used_at'=>now()]);
            $this->withHeaders(['Authorization'=>'Bearer '.$plain])->getJson('/api/dashboard/kota/map')->assertForbidden();
        }
    }
}
