<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ASPECTS = [
        'PROTO_EVIDENCE' => 'Kelengkapan bukti', 'PROTO_SPEC_UNIT' => 'Kesesuaian spesifikasi/satuan',
        'PROTO_PRICE_BASIS' => 'Dasar harga/biaya', 'PROTO_CALCULATION' => 'Perhitungan',
        'PROTO_COMPARISON' => 'Kesesuaian data pembanding',
    ];

    public function up(): void
    {
        // Approved by the developer on 2 Nov 2026 as prototype aspects, not a final SOP.
        foreach (self::ASPECTS as $code => $name) {
            DB::table('review_aspects')->insertOrIgnore(['code' => $code, 'name' => $name, 'is_required' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Never remove an aspect referenced by a review snapshot.
        DB::table('review_aspects')->whereIn('code', array_keys(self::ASPECTS))
            ->whereNotIn('id', DB::table('review_results')->select('review_aspect_id'))->delete();
    }
};
