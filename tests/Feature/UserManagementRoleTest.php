<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Region;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementRoleTest extends TestCase
{
    use RefreshDatabase;

    private function authHeaders(string $role, string $suffix): array
    {
        $regionId = match ($role) {
            'kota' => Region::where('level','kota')->value('id'),
            'kecamatan' => Region::where('code','32.73.05')->value('id'),
            default => Region::where('code','32.73.05.1002')->value('id'),
        };
        $user=User::create([
            'name'=>ucfirst($role).' Test',
            'username'=>$role.'.'.$suffix,
            'email'=>$role.'.'.$suffix.'@example.com',
            'password'=>Hash::make('StrongPass123!'),
            'role'=>$role,
            'region_id'=>$regionId,
            'is_active'=>true,
        ]);
        $plain='token-'.$role.'-'.$suffix;
        ApiToken::create([
            'user_id'=>$user->id,
            'token_hash'=>hash('sha256',$plain),
            'expires_at'=>now()->addHour(),
            'absolute_expires_at'=>now()->addHours(2),
            'last_used_at'=>now(),
        ]);
        return ['Authorization'=>'Bearer '.$plain];
    }

    public function test_kota_can_create_kecamatan_and_kelurahan_accounts_with_matching_regions(): void
    {
        $headers=$this->authHeaders('kota','root');
        $regions=[
            'kecamatan'=>Region::where('code','32.73.05')->firstOrFail(),
            'kelurahan'=>Region::where('code','32.73.05.1002')->firstOrFail(),
        ];

        foreach(['kecamatan','kelurahan'] as $role) {
            $this->withHeaders($headers)->postJson('/api/users',[
                'name'=>'Akun '.ucfirst($role),
                'username'=>$role.'.baru',
                'email'=>$role.'.baru@example.com',
                'role'=>$role,
                'region_id'=>$regions[$role]->id,
                'password'=>'StrongPass123!',
            ])->assertCreated()->assertJsonPath('role',$role)->assertJsonPath('region.id',$regions[$role]->id);
        }
    }

    public function test_kota_cannot_create_another_kota_account_from_user_management(): void
    {
        $headers=$this->authHeaders('kota','root2');
        $this->withHeaders($headers)->postJson('/api/users',[
            'name'=>'Kota Baru',
            'username'=>'kota.baru',
            'email'=>'kota.baru@example.com',
            'role'=>'kota',
            'region_id'=>Region::where('level','kota')->value('id'),
            'password'=>'StrongPass123!',
        ])->assertStatus(422);
    }

    public function test_kota_cannot_bind_role_to_wrong_region_level(): void
    {
        $headers=$this->authHeaders('kota','root3');
        $this->withHeaders($headers)->postJson('/api/users',[
            'name'=>'Kecamatan Salah Wilayah','username'=>'kecamatan.salah','email'=>'kecamatan.salah@example.com',
            'role'=>'kecamatan','region_id'=>Region::where('level','kelurahan')->value('id'),'password'=>'StrongPass123!',
        ])->assertStatus(422);
    }

    public function test_kecamatan_and_kelurahan_cannot_manage_users(): void
    {
        foreach(['kecamatan','kelurahan'] as $role) {
            $headers=$this->authHeaders($role,'limited');
            $this->withHeaders($headers)->getJson('/api/users')->assertForbidden();
            $this->withHeaders($headers)->postJson('/api/users',[
                'name'=>'Tidak Boleh',
                'username'=>'blocked.'.$role,
                'email'=>'blocked.'.$role.'@example.com',
                'role'=>'kelurahan',
                'region_id'=>Region::where('level','kelurahan')->value('id'),
                'password'=>'StrongPass123!',
            ])->assertForbidden();
        }
    }
}
