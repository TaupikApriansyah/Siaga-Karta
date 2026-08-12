<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'username', 'role', 'is_active', 'password'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['email_verified_at' => 'datetime', 'is_active' => 'boolean', 'password' => 'hashed']; }
    public function apiTokens() { return $this->hasMany(ApiToken::class); }
}
