<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'username', 'role', 'region_id', 'is_active', 'password'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at'=>'datetime','is_active'=>'boolean','password'=>'hashed'];
    }

    public static function normalizeIdentity(?string $value): string
    {
        return Str::lower(trim((string)$value));
    }

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = self::normalizeIdentity($value);
    }

    public function setUsernameAttribute($value): void
    {
        $normalized = self::normalizeIdentity($value);
        $this->attributes['username'] = $normalized === '' ? null : $normalized;
    }

    public function apiTokens() { return $this->hasMany(ApiToken::class); }
    public function region() { return $this->belongsTo(Region::class); }
}
