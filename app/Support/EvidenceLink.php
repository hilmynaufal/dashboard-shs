<?php

namespace App\Support;

/** Tautan bukti dukung: hanya HTTPS ke Google Drive/Docs, tanpa kredensial, port, atau spasi. */
final class EvidenceLink
{
    public const HOSTS = ['drive.google.com', 'docs.google.com'];

    public const MESSAGE = 'Tautan harus berupa alamat Google Drive (https://drive.google.com/… atau https://docs.google.com/…) yang menuju berkas atau folder.';

    /** Mengembalikan tautan yang dinormalkan, atau null bila tidak diterima. */
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) { return null; }
        $value = trim($value);
        if ($value === '' || strlen($value) > 2000 || preg_match('/[\x00-\x20\x7f]/', $value)) { return null; }
        $parts = parse_url($value);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) { return null; }
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($host, self::HOSTS, true)) { return null; }
        $path = $parts['path'] ?? '';
        if (($path === '' || $path === '/') && empty($parts['query'])) { return null; }

        return 'https://'.$host.$path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
