<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateInitialAdmin extends Command
{
    protected $signature = 'siagakarta:create-admin {--name=} {--email=} {--username=}';
    protected $description = 'Membuat akun Kota secara aman tanpa password default atau password di shell history.';

    public function handle(): int
    {
        $name=(string)($this->option('name') ?: $this->ask('Nama Pengelola Kota'));
        $email=User::normalizeIdentity((string)($this->option('email') ?: $this->ask('Email Pengelola Kota')));
        $username=User::normalizeIdentity((string)($this->option('username') ?: $this->ask('Username Pengelola Kota')));
        $password=(string)$this->secret('Password Pengelola Kota (minimal 10 karakter)');
        $confirm=(string)$this->secret('Ulangi password');

        if($password!==$confirm) {
            $this->error('Konfirmasi password tidak sama.');
            return self::FAILURE;
        }

        $data=compact('name','email','username','password');
        $validator=Validator::make($data,[
            'name'=>'required|string|max:120',
            'email'=>'required|email|max:190|unique:users,email',
            'username'=>'required|string|min:4|max:60|regex:/^[a-z0-9._-]+$/|unique:users,username',
            'password'=>'required|string|min:10|max:200',
        ]);
        if($validator->fails()) {
            foreach($validator->errors()->all() as $message) $this->error($message);
            return self::FAILURE;
        }

        $user=User::create([
            'name'=>$name,'email'=>$email,'username'=>$username,'password'=>$password,
            'role'=>'kota','region_id'=>Region::where('level','kota')->value('id'),'is_active'=>true,
        ]);
        $this->info("Akun Kota {$user->username} berhasil dibuat.");
        return self::SUCCESS;
    }
}
