<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class RecordVersion
{
    public static function of(array $state): string
    {
        return hash('sha256', json_encode($state, JSON_THROW_ON_ERROR));
    }

    public static function assertCurrent(string $version, array $state): void
    {
        if (! hash_equals(self::of($state), $version)) {
            throw ValidationException::withMessages(['record_version' => 'Data telah berubah sejak halaman dibuka. Muat ulang data sebelum menyimpan.'])->status(409);
        }
    }
}
