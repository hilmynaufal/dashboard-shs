<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Membaca workbook usulan format Inspektorat (lembar Data_SSH / Data_SBU) menjadi baris item per pengusul.
 * Lembar lain, termasuk lembar "REVIU" berisi catatan kerja, tidak dibaca. Tidak ada formula yang dihitung.
 */
final class InspectorateWorkbook
{
    public const SHEETS = ['SHS' => ['data_ssh', 'data_shs'], 'SBU' => ['data_sbu']];
    public const NO_OWNER = 'PENGUSUL BELUM TERCANTUM';
    private const REQUIRED = ['code' => 'kode', 'name' => 'uraian', 'unit' => 'satuan', 'price' => 'harga', 'owner' => 'pengusul'];
    private const ORIGINS = ['data lama' => 'existing', 'data baru' => 'new'];

    /** @return array{rows: list<array>, groups: int, sheets: array<string,string>} */
    public static function parse(string $path): array
    {
        $reader = new ProposalXlsx;
        $first = $reader->read($path, 0);
        $picked = [];
        foreach (self::SHEETS as $type => $aliases) {
            foreach ($first['sheets'] as $index => $name) {
                if (in_array(mb_strtolower(trim($name)), $aliases, true)) { $picked[$type] = $index; break; }
            }
        }
        if (!$picked) {
            throw ValidationException::withMessages(['file' => 'Lembar Data_SSH atau Data_SBU tidak ditemukan. Periksa nama lembar pada berkas.']);
        }
        $rows = []; $groups = 0; $sheets = [];
        foreach ($picked as $type => $index) {
            $sheet = $index === 0 ? $first : $reader->read($path, $index);
            $parsed = self::sheet($type, $sheet['sheet'], $sheet['rows']);
            array_push($rows, ...$parsed['rows']);
            $groups += $parsed['groups']; $sheets[$type] = $sheet['sheet'];
            unset($sheet, $parsed);
            if ($index === 0) { $first = ['sheets' => $first['sheets']]; }
        }

        return ['rows' => $rows, 'groups' => $groups, 'sheets' => $sheets];
    }

    private static function sheet(string $type, string $name, array $source): array
    {
        [$header, $columns] = self::columns($name, $source);
        $rows = []; $groups = 0;
        foreach ($source as $number => $cells) {
            if ($number <= $header) { continue; }
            $get = fn (?int $column): string => $column === null ? '' : trim((string)($cells[$column]['value'] ?? ''));
            $price = $get($columns['price']); $spec = $get($columns['spec']); $unit = $get($columns['unit']);
            if ($price === '' && $spec === '' && $unit === '') {
                if ($get($columns['code']) !== '' || $get($columns['name']) !== '') { $groups++; }
                continue;
            }
            $errors = []; $code = $get($columns['code']); $title = $get($columns['name']);
            if ($code === '') { $errors[] = 'Kode kosong.'; }
            elseif (mb_strlen($code) > 60) { $errors[] = 'Kode lebih dari 60 karakter.'; }
            elseif (($cells[$columns['code']]['type'] ?? 's') === 'n') { $errors[] = 'Kode berformat angka; ubah kolom menjadi teks agar kode tetap utuh.'; }
            if ($title === '' || mb_strlen($title) > 255) { $errors[] = $title === '' ? 'Uraian kosong.' : 'Uraian lebih dari 255 karakter.'; }
            if ($unit === '' || mb_strlen($unit) > 255) { $errors[] = $unit === '' ? 'Satuan kosong.' : 'Satuan lebih dari 255 karakter.'; }
            if (mb_strlen($spec) > 10000) { $errors[] = 'Spesifikasi lebih dari 10.000 karakter.'; }
            try {
                $money = ProposalMoney::normalize($price);
                if (!preg_match('/[1-9]/', $money)) { $errors[] = 'Harga harus lebih dari nol.'; }
            } catch (ValidationException) { $money = null; $errors[] = 'Harga bukan angka yang sah.'; }
            $accounts = [];
            foreach ($columns['accounts'] as $column) {
                $account = $get($column);
                if ($account !== '') { $accounts[$account] = $account; }
            }
            if (count($accounts) > 50) { $errors[] = 'Lebih dari 50 rekening pada satu item.'; }
            $tkdn = str_replace(',', '.', rtrim($get($columns['tkdn']), '% '));
            $data = ['code' => $code, 'name' => $title, 'spec' => $spec, 'unit' => $unit, 'price' => $money, 'accounts' => array_values($accounts),
                'tkdn' => is_numeric($tkdn) && $tkdn >= 0 && $tkdn <= 100 ? number_format((float)$tkdn, 2, '.', '') : null,
                'origin' => self::ORIGINS[mb_strtolower($get($columns['origin']))] ?? null,
                'note' => ($note = $get($columns['note'])) === '' ? null : mb_substr($note, 0, 10000)];
            foreach (self::owners($get($columns['owner'])) as $owner) {
                $rows[] = ['type' => $type, 'row_number' => (int)$number, 'owner' => $owner, 'data' => $data, 'error' => $errors ? implode(' ', $errors) : null];
            }
        }

        return ['rows' => $rows, 'groups' => $groups];
    }

    /** Baris dengan beberapa pengusul digandakan per pengusul; ASB (analisis standar belanja) dilepas bila ada pengusul lain. */
    public static function owners(string $raw): array
    {
        $owners = array_values(array_unique(array_filter(array_map(fn ($owner) => mb_substr(trim(preg_replace('/\s+/u', ' ', mb_strtoupper($owner))), 0, 120), explode(';', $raw)))));
        if (count($owners) > 1) { $owners = array_values(array_diff($owners, ['ASB'])); }

        return $owners ?: [self::NO_OWNER];
    }

    private static function columns(string $name, array $source): array
    {
        $normalize = fn (mixed $value): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string)$value)));
        foreach (array_slice($source, 0, 15, true) as $number => $cells) {
            $found = [];
            foreach ($cells as $column => $cell) { $found[$normalize($cell['value'] ?? '')] ??= $column; }
            if (isset($found['kode'], $found['uraian'], $found['harga'])) {
                $columns = ['spec' => $found['spek'] ?? $found['spesifikasi'] ?? null, 'tkdn' => $found['tkdn'] ?? null, 'origin' => $found['tipe'] ?? null,
                    'note' => $found['keterangan'] ?? null, 'accounts' => []];
                foreach (self::REQUIRED as $field => $label) { $columns[$field] = $found[$label] ?? null; }
                foreach ($found as $label => $column) { if (preg_match('/\Arekening(_\d+)?\z/', $label)) { $columns['accounts'][] = $column; } }
                $missing = array_keys(array_filter(self::REQUIRED, fn ($label, $field) => $columns[$field] === null, ARRAY_FILTER_USE_BOTH));
                if ($missing) {
                    throw ValidationException::withMessages(['file' => 'Kolom wajib tidak ditemukan pada lembar '.$name.': '.implode(', ', array_map(fn ($field) => self::REQUIRED[$field], $missing)).'.']);
                }

                return [$number, $columns];
            }
        }
        throw ValidationException::withMessages(['file' => 'Baris judul (kode, uraian, satuan, harga, pengusul) tidak ditemukan pada lembar '.$name.'.']);
    }
}
