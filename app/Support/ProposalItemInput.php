<?php

namespace App\Support;

use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;

final class ProposalItemInput
{
    private array $catalogs = [];

    public function __construct(?array $lockedIds = null)
    {
        foreach (['code' => 'item_classifications', 'unit' => 'units', 'account' => 'account_codes'] as $field => $table) {
            $query = DB::table($table)->where('is_active', true)->orderBy('id');
            if ($lockedIds !== null) { $query->whereIn('id', $lockedIds[$field] ?? [])->lockForUpdate(); }
            foreach ($query->get(['id', 'code', 'name']) as $row) {
                $this->catalogs[$field]['codes'][mb_strtolower($row->code)] = (array) $row;
                if ($field === 'unit') {
                    $key = mb_strtolower($row->name);
                    $this->catalogs[$field]['names'][$key] = array_key_exists($key, $this->catalogs[$field]['names'] ?? []) ? null : (array) $row;
                }
            }
        }
    }

    public function validate(array $raw, bool $complete = false): array
    {
        $data = Validator::make($raw, [
            'name' => ['required', 'string', 'max:255'], 'spec' => ['nullable', 'string', 'max:10000'],
            'code' => ['required', 'string', 'max:60'], 'unit' => ['required', 'string', 'max:255'],
            'account' => ['nullable', 'string', 'max:3200'], 'price' => ['nullable'],
        ], AccountValidation::MESSAGES, ['name' => 'Uraian', 'spec' => 'Spesifikasi', 'code' => 'Kode barang', 'unit' => 'Satuan', 'account' => 'Rekening', 'price' => 'Harga'])->validate();
        $errors = [];
        $classification = $this->catalogs['code']['codes'][mb_strtolower(trim($data['code']))] ?? null;
        $unitKey = mb_strtolower(trim($data['unit']));
        $unit = $this->catalogs['unit']['codes'][$unitKey] ?? $this->catalogs['unit']['names'][$unitKey] ?? null;
        if (! $classification) { $errors['code'] = 'Kode barang tidak ditemukan pada referensi aktif. Hubungi Admin/BKAD untuk melengkapinya.'; }
        if (! $unit) { $errors['unit'] = 'Satuan tidak ditemukan atau namanya tidak unik. Gunakan kode satuan aktif.'; }
        $accounts = [];
        $codes = array_values(array_unique(array_filter(array_map('trim', preg_split('/[;,|\r\n]+/', $data['account'] ?? '')),fn($code)=>$code!=='')));
        if (count($codes) > 50) { $errors['account'] = 'Maksimal 50 rekening pada satu item.'; }
        foreach ($codes as $code) {
            $entry = $this->catalogs['account']['codes'][mb_strtolower($code)] ?? null;
            if (! $entry) { $errors['account'] = 'Rekening '.$code.' tidak ditemukan pada referensi aktif.'; }
            else { $accounts[$entry['id']] = $entry; }
        }
        try { $price = ProposalMoney::normalize($data['price'] ?? null); }
        catch (ValidationException $error) { $errors['price'] = $error->errors()['price'][0]; $price = '0.00'; }
        if ($complete) {
            if (trim($data['spec'] ?? '') === '') { $errors['spec'] = 'Spesifikasi wajib dilengkapi sebelum pengiriman.'; }
            if (! preg_match('/[1-9]/', $price)) { $errors['price'] = 'Harga harus lebih dari nol sebelum pengiriman.'; }
            if (! $accounts) { $errors['account'] ??= 'Sedikitnya satu rekening wajib dipilih sebelum pengiriman.'; }
        }
        if ($errors) { throw ValidationException::withMessages($errors); }

        return ['description' => trim($data['name']), 'specification' => trim($data['spec'] ?? ''), 'proposed_price' => $price,
            'classification' => $classification, 'unit' => $unit, 'accounts' => array_values($accounts)];
    }

    public static function fields(object $version): array
    {
        $snapshot = is_array($version->reference_snapshot) ? $version->reference_snapshot : json_decode($version->reference_snapshot, true);

        return ['name' => $version->description, 'spec' => $version->specification,
            'code' => $snapshot['classification']['code'] ?? '', 'unit' => $snapshot['unit']['code'] ?? '',
            'price' => $version->proposed_price, 'account' => implode('; ', array_column($snapshot['accounts'] ?? [], 'code'))];
    }
}
