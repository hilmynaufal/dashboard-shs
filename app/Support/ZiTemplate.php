<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Penyuntingan dan pemeriksaan draf instrumen ZI. Bekerja pada array butir dan aturan, tanpa basis data. */
final class ZiTemplate
{
    public const TYPES = ['Ya/Tidak', 'A/B/C', 'A/B/C/D', 'A/B/C/D/E', '%'];
    public const FORMULAS = ['ratio'=>'Rasio (pembilang ÷ penyebut)', 'reduction'=>'Penurunan dari tahun sebelumnya'];
    public const OPS = ['thresholds', 'area.save', 'area.delete', 'group.save', 'group.delete', 'item.save', 'item.delete'];

    public static function number(float|int $value): string
    {
        return rtrim(rtrim(number_format($value, 4, ',', '.'), '0'), ',');
    }

    public static function groupName(array $group, array $items): string
    {
        return $group['name'] ?? ($items[$group['items'][0] ?? null]['group'] ?? $group['key']);
    }

    /** Daftar masalah yang menghalangi penerbitan; kosong berarti siap terbit. */
    public static function validate(array $items, array $rules): array
    {
        $errors = [];
        foreach (['WBK', 'WBBM'] as $target) {
            $value = $rules['thresholds'][$target] ?? null;
            if (!is_numeric($value) || $value <= 0 || $value > 1) {
                $errors[] = "Ambang $target harus lebih dari 0% dan paling banyak 100%.";
            }
        }
        if (!($rules['areas'] ?? [])) {
            $errors[] = 'Instrumen belum memiliki area.';
        }
        $placed = [];
        foreach ($rules['areas'] ?? [] as $area) {
            $label = 'Area '.$area['name'];
            if (!($area['max'] > 0)) {
                $errors[] = "$label: bobot area harus lebih dari 0.";
            }
            if (!$area['groups']) {
                $errors[] = "$label belum memiliki kelompok.";
                continue;
            }
            $sum = array_sum(array_column($area['groups'], 'weight'));
            if (abs($sum - $area['max']) > 1e-9) {
                $errors[] = "$label: jumlah bobot kelompok ".self::number($sum).' tidak sama dengan bobot area '.self::number($area['max']).'.';
            }
            $keys = array_column($area['groups'], 'key');
            $resolved = [];
            $pending = $area['groups'];
            foreach ($area['groups'] as $group) {
                $name = 'Kelompok '.self::groupName($group, $items);
                if (!($group['weight'] > 0)) {
                    $errors[] = "$name: bobot harus lebih dari 0.";
                }
                if (!$group['items']) {
                    $errors[] = "$name belum memiliki butir.";
                }
                foreach ($group['items'] as $id) {
                    if (!isset($items[$id])) {
                        $errors[] = "$name merujuk butir $id yang tidak ada.";
                    } elseif (isset($placed[$id])) {
                        $errors[] = "Butir $id masuk lebih dari satu kelompok.";
                    }
                    $placed[$id] = true;
                }
                foreach ($group['include'] ?? [] as $key) {
                    if (!in_array($key, $keys, true)) {
                        $errors[] = "$name bergantung pada kelompok \"$key\" yang tidak ada di area ini.";
                    }
                }
            }
            while ($pending) {
                $before = count($pending);
                foreach ($pending as $i => $group) {
                    if (!array_diff(array_intersect($group['include'] ?? [], $keys), $resolved)) {
                        $resolved[] = $group['key'];
                        unset($pending[$i]);
                    }
                }
                if (count($pending) === $before) {
                    $errors[] = "$label: ketergantungan antarkelompok melingkar.";
                    break;
                }
            }
        }
        foreach ($items as $id => $item) {
            if (!isset($placed[$id])) {
                $errors[] = "Butir $id belum masuk kelompok mana pun.";
            }
            if (trim($item['title'] ?? '') === '') {
                $errors[] = "Butir $id belum memiliki redaksi.";
            }
            if (!in_array($item['type'] ?? null, self::TYPES, true)) {
                $errors[] = "Butir $id memakai tipe jawaban yang belum didukung.";
                continue;
            }
            $lines = count($item['rubric'] ?? []);
            $need = in_array($item['type'], ['Ya/Tidak', '%'], true) ? null : count(explode('/', $item['type']));
            if ($need === null ? $lines < 1 : $lines !== $need) {
                $errors[] = "Butir $id: kriteria harus ".($need === null ? 'diisi minimal satu baris' : "berisi $need baris, satu per pilihan").'.';
            }
            if (($item['formula'] ?? 'ratio') === 'reduction' && array_slice(array_column($item['fields'] ?? [], 'name'), 0, 2) !== ['previous', 'current']) {
                $errors[] = "Butir $id: rumus penurunan memerlukan isian tahun sebelumnya dan tahun ini.";
            }
        }
        return array_values(array_unique($errors));
    }

    /** Menerapkan satu perubahan pada draf dan mengembalikan uraian untuk riwayat. */
    public static function apply(array &$items, array &$rules, string $op, array $in): string
    {
        $decimal = fn ($v) => is_string($v) ? str_replace(',', '.', trim($v)) : $v;
        foreach (['wbk', 'wbbm', 'max', 'weight'] as $field) {
            if (isset($in[$field])) {
                $in[$field] = $decimal($in[$field]);
            }
        }
        $check = fn (array $r) => Validator::make($in, $r)->validate();
        $areaIndex = function (?string $key) use ($rules): int {
            $i = array_search($key, array_column($rules['areas'], 'key'), true);
            if ($i === false) {
                throw ValidationException::withMessages(['area'=>'Area tidak ditemukan. Muat ulang halaman.']);
            }
            return $i;
        };
        $groupIndex = function (int $a, ?string $key) use ($rules): int {
            $i = array_search($key, array_column($rules['areas'][$a]['groups'], 'key'), true);
            if ($i === false) {
                throw ValidationException::withMessages(['group'=>'Kelompok tidak ditemukan. Muat ulang halaman.']);
            }
            return $i;
        };
        $unique = function (string $name, array $taken): string {
            $base = Str::slug($name) ?: 'bagian';
            for ($key = $base, $n = 2; in_array($key, $taken, true); $n++) {
                $key = $base.'-'.$n;
            }
            return $key;
        };

        switch ($op) {
            case 'thresholds':
                $d = $check(['wbk'=>'required|numeric|gt:0|max:100', 'wbbm'=>'required|numeric|gt:0|max:100']);
                $rules['thresholds'] = ['WBK'=>$d['wbk'] / 100, 'WBBM'=>$d['wbbm'] / 100];
                return 'Ambang diubah: WBK '.self::number($d['wbk']).'%, WBBM '.self::number($d['wbbm']).'%.';

            case 'area.save':
                $d = $check(['area'=>'nullable|string', 'name'=>'required|string|max:150', 'max'=>'required|numeric|gt:0|max:100']);
                if ($d['area'] ?? null) {
                    $a = $areaIndex($d['area']);
                    $rules['areas'][$a] = ['name'=>trim($d['name']), 'max'=>(float)$d['max']] + $rules['areas'][$a];
                    return 'Area "'.trim($d['name']).'" diubah, bobot '.self::number($d['max']).'.';
                }
                $rules['areas'][] = ['key'=>$unique($d['name'], array_column($rules['areas'], 'key')), 'name'=>trim($d['name']), 'max'=>(float)$d['max'], 'groups'=>[]];
                return 'Area "'.trim($d['name']).'" ditambahkan, bobot '.self::number($d['max']).'.';

            case 'area.delete':
                $a = $areaIndex($in['area'] ?? null);
                if ($rules['areas'][$a]['groups']) {
                    throw ValidationException::withMessages(['area'=>'Hapus atau pindahkan kelompok di area ini terlebih dahulu.']);
                }
                $name = $rules['areas'][$a]['name'];
                array_splice($rules['areas'], $a, 1);
                return "Area \"$name\" dihapus.";

            case 'group.save':
                $d = $check(['area'=>'required|string', 'group'=>'nullable|string', 'name'=>'required|string|max:200', 'weight'=>'required|numeric|gt:0|max:100']);
                $a = $areaIndex($d['area']);
                $name = trim($d['name']);
                if ($d['group'] ?? null) {
                    $g = $groupIndex($a, $d['group']);
                    $rules['areas'][$a]['groups'][$g] = ['name'=>$name, 'weight'=>(float)$d['weight']] + $rules['areas'][$a]['groups'][$g];
                    foreach ($rules['areas'][$a]['groups'][$g]['items'] as $id) {
                        $items[$id]['group'] = $name;
                    }
                    return "Kelompok \"$name\" diubah, bobot ".self::number($d['weight']).'.';
                }
                $rules['areas'][$a]['groups'][] = ['key'=>$unique($name, array_column($rules['areas'][$a]['groups'], 'key')), 'name'=>$name, 'items'=>[], 'weight'=>(float)$d['weight']];
                return "Kelompok \"$name\" ditambahkan, bobot ".self::number($d['weight']).'.';

            case 'group.delete':
                $a = $areaIndex($in['area'] ?? null);
                $g = $groupIndex($a, $in['group'] ?? null);
                $group = $rules['areas'][$a]['groups'][$g];
                if ($group['items']) {
                    throw ValidationException::withMessages(['group'=>'Hapus atau pindahkan butir di kelompok ini terlebih dahulu.']);
                }
                foreach ($rules['areas'][$a]['groups'] as $other) {
                    if (in_array($group['key'], $other['include'] ?? [], true)) {
                        throw ValidationException::withMessages(['group'=>'Kelompok ini dipakai dalam perhitungan kelompok lain.']);
                    }
                }
                array_splice($rules['areas'][$a]['groups'], $g, 1);
                return 'Kelompok "'.self::groupName($group, $items).'" dihapus.';

            case 'item.save':
                $d = $check(['original'=>'nullable|integer', 'id'=>'required|integer|between:1,9999', 'placement'=>'required|string',
                    'title'=>'required|string|max:1000', 'type'=>['required', Rule::in(self::TYPES)], 'rubric'=>'required|string|max:10000',
                    'formula'=>['nullable', Rule::in(array_keys(self::FORMULAS))], 'label_a'=>'nullable|string|max:150', 'label_b'=>'nullable|string|max:150']);
                $new = !($d['original'] ?? null);
                $id = $new ? (int)$d['id'] : (int)$d['original'];
                if ($new ? isset($items[$id]) : !isset($items[$id])) {
                    throw ValidationException::withMessages(['id'=>$new ? "Nomor butir $id sudah dipakai." : 'Butir tidak ditemukan. Muat ulang halaman.']);
                }
                [$areaKey, $groupKey] = array_pad(explode('/', $d['placement'], 2), 2, null);
                $a = $areaIndex($areaKey);
                $g = $groupIndex($a, $groupKey);
                $groupName = self::groupName($rules['areas'][$a]['groups'][$g], $items);
                $item = ['group'=>$groupName, 'title'=>trim($d['title']), 'type'=>$d['type'],
                    'rubric'=>array_values(array_filter(array_map('trim', preg_split('/\\R/', $d['rubric'])), 'strlen'))];
                if ($d['type'] === '%') {
                    if (($d['formula'] ?? 'ratio') === 'reduction') {
                        $item['formula'] = 'reduction';
                        $item['fields'] = array_merge([
                            ['name'=>'previous', 'label'=>trim($d['label_a'] ?? '') ?: 'Jumlah tahun sebelumnya', 'required'=>true],
                            ['name'=>'current', 'label'=>trim($d['label_b'] ?? '') ?: 'Jumlah tahun ini', 'required'=>true],
                        ], array_slice(($items[$id]['formula'] ?? null) === 'reduction' ? $items[$id]['fields'] : [], 2));
                    } else {
                        $item['labels'] = ['numerator'=>trim($d['label_a'] ?? '') ?: 'Pembilang', 'denominator'=>trim($d['label_b'] ?? '') ?: 'Penyebut'];
                    }
                }
                // Nama kelompok disimpan di aturan agar tidak hilang ketika butir pertamanya pindah.
                $rules['areas'][$a]['groups'][$g]['name'] = $groupName;
                foreach ($rules['areas'] as &$area) {
                    foreach ($area['groups'] as &$group) {
                        if (!isset($group['name']) && in_array($id, $group['items'], true)) {
                            $group['name'] = self::groupName($group, $items);
                        }
                        $group['items'] = array_values(array_diff($group['items'], [$id]));
                    }
                }
                unset($area, $group);
                $rules['areas'][$a]['groups'][$g]['items'][] = $id;
                sort($rules['areas'][$a]['groups'][$g]['items']);
                $items[$id] = $item;
                ksort($items);
                return "Butir $id ".($new ? 'ditambahkan' : 'diubah').' di kelompok "'.$item['group'].'".';

            case 'item.delete':
                $id = (int)($in['original'] ?? 0);
                if (!isset($items[$id])) {
                    throw ValidationException::withMessages(['id'=>'Butir tidak ditemukan. Muat ulang halaman.']);
                }
                foreach ($rules['areas'] as &$area) {
                    foreach ($area['groups'] as &$group) {
                        if (!isset($group['name']) && in_array($id, $group['items'], true)) {
                            $group['name'] = self::groupName($group, $items);
                        }
                        $group['items'] = array_values(array_diff($group['items'], [$id]));
                    }
                }
                unset($area, $group, $items[$id]);
                return "Butir $id dihapus.";
        }
        abort(422, 'Perubahan tidak dikenal.');
    }
}
