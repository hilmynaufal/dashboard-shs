<?php

namespace App\Support;

final class ReferenceCatalog
{
    public const TYPES = [
        'barang' => ['table' => 'item_classifications', 'label' => 'Kode barang'],
        'rekening' => ['table' => 'account_codes', 'label' => 'Rekening'],
        'satuan' => ['table' => 'units', 'label' => 'Satuan'],
    ];

    public static function type(string $kind): array
    {
        abort_unless(isset(self::TYPES[$kind]), 404);

        return self::TYPES[$kind];
    }

    public static function searchPattern(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
