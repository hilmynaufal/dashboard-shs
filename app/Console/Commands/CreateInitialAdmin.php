<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\{AccountActivity, AccountValidation};
use Database\Seeders\AccessControlSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Validator};

class CreateInitialAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Membuat Admin pertama secara interaktif tanpa kata sandi bawaan';

    public function handle(): int
    {
        if (DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->where('roles.code', 'admin')->exists()) {
            $this->error('Admin sudah tersedia. Gunakan halaman Kelola akun untuk membuat akun berikutnya.');

            return self::FAILURE;
        }
        $data = ['name' => $this->ask('Nama Admin'), 'email' => mb_strtolower(trim((string) $this->ask('Email Admin'))),
            'password' => $this->secret('Kata sandi (minimal 12 karakter, huruf besar/kecil dan angka)'),
            'password_confirmation' => $this->secret('Konfirmasi kata sandi')];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => AccountValidation::passwordRules(),
        ], AccountValidation::MESSAGES, AccountValidation::ATTRIBUTES);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) { $this->error($error); }

            return self::FAILURE;
        }
        DB::transaction(function () use ($data): void {
            // Serialize initial provisioning on the role row, including concurrent CLI runs.
            app(AccessControlSeeder::class)->run();
            $role = DB::table('roles')->where('code', 'admin')->lockForUpdate()->first();
            if (DB::table('role_user')->where('role_id', $role->id)->exists()) {
                throw new \RuntimeException('Admin sudah dibuat oleh proses lain.');
            }
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            DB::table('role_user')->insert(['role_id' => $role->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()]);
            AccountActivity::record($user->id, 'account.initial_admin_created', $user->id);
        });
        $this->info('Admin pertama berhasil dibuat. Masuk melalui /masuk.');

        return self::SUCCESS;
    }
}
