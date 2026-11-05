<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Data referensi dari KKR_REVIU_SHS_TA_2026.xlsx (Data_SSH & Data_SBU):
 * kode barang (hierarki), satuan, dan kode rekening. Idempoten (updateOrInsert).
 * Nama rekening belum ada di Excel, sementara diisi sama dengan kodenya.
 */
class ReferenceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (['units', 'account_codes'] as $table) {
                foreach ($this->rows($table) as $r) {
                    DB::table($table)->updateOrInsert(['code' => $r['code']], ['name' => $r['name'], 'is_active' => true, 'updated_at' => now()]);
                }
            }
            // Urut dari kode terpendek, sehingga induk selalu masuk lebih dulu.
            foreach ($this->rows('item_classifications') as $r) {
                $parent = $r['parent_code'] === '' ? null : DB::table('item_classifications')->where('code', $r['parent_code'])->value('id');
                DB::table('item_classifications')->updateOrInsert(['code' => $r['code']], ['name' => $r['name'], 'parent_id' => $parent, 'is_active' => true, 'updated_at' => now()]);
            }
        });
    }

    private function rows(string $name): \Generator
    {
        $h = fopen(__DIR__."/data/{$name}.csv", 'r');
        $head = fgetcsv($h, 0, ',', '"', '');
        while (($row = fgetcsv($h, 0, ',', '"', '')) !== false) {
            yield array_combine($head, $row);
        }
        fclose($h);
    }
}
