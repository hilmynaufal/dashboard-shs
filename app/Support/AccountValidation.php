<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

final class AccountValidation
{
    public static function passwordRules(): array
    {
        return ['required', 'string', 'max:72', 'confirmed', Password::min(12)->mixedCase()->numbers(),
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && strlen($value) > 72) {
                    $fail('Kata sandi terlalu panjang untuk disimpan; gunakan maksimal 72 byte.');
                }
            },
        ];
    }

    public const MESSAGES = [
        'required' => ':attribute wajib diisi.',
        'string' => ':attribute harus berupa teks.',
        'email' => 'Format alamat email belum sesuai.',
        'max.string' => ':attribute terlalu panjang (maksimal :max karakter).',
        'min.string' => ':attribute minimal :min karakter.',
        'confirmed' => 'Konfirmasi kata sandi tidak sama.',
        'current_password' => 'Kata sandi saat ini tidak sesuai.',
        'unique' => ':attribute sudah digunakan.',
        'exists' => ':attribute tidak tersedia atau tidak aktif.',
        'in' => ':attribute tidak tersedia.',
        'boolean' => ':attribute harus aktif atau nonaktif.',
        'integer' => ':attribute harus dipilih dari daftar yang tersedia.',
        'prohibited' => ':attribute tidak dapat diubah melalui formulir ini.',
        'password.mixed' => 'Kata sandi harus memuat huruf besar dan huruf kecil.',
        'password.numbers' => 'Kata sandi harus memuat angka.',
    ];

    public const ATTRIBUTES = [
        'name' => 'Nama', 'email' => 'Email', 'position' => 'Jabatan',
        'password' => 'Kata sandi', 'current_password' => 'Kata sandi saat ini',
        'role' => 'Peran', 'roles' => 'Peran', 'organization_id' => 'SKPD',
        'is_active' => 'Status akun',
    ];
}
