<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class ProposalMoney
{
    public static function normalize(mixed $value): string
    {
        if ($value === null || $value === '') { return '0.00'; }
        if (! is_string($value) && ! is_int($value)) { self::invalid(); }
        $value = preg_replace('/\s+/u', '', preg_replace('/^Rp\.?\s*/i', '', trim((string) $value)));
        if (preg_match('/\A\d{1,3}(\.\d{3})+(,\d{1,2})?\z/', $value)) { $value = str_replace('.', '', $value); }
        $value = str_replace(',', '.', $value);
        if (! preg_match('/\A\d+(?:\.\d{1,2})?\z/', $value)) { self::invalid(); }
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 18) { self::invalid(); }

        return $whole.'.'.str_pad($fraction, 2, '0');
    }

    public static function display(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value), 2, '00');

        return 'Rp '.preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $whole).($fraction === '00' ? '' : ','.$fraction);
    }

    private static function invalid(): never
    {
        throw ValidationException::withMessages(['price' => 'Harga harus berupa angka positif atau nol, maksimal 18 digit sebelum koma dan 2 angka desimal.']);
    }
}
