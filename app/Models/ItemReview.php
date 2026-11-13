<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemReview extends Model
{
    public const RESULTS = [
        'not_assessed' => 'Belum dinilai', 'compliant' => 'Sesuai', 'non_compliant' => 'Tidak sesuai',
        'insufficient_evidence' => 'Belum cukup bukti', 'not_applicable' => 'Tidak berlaku',
    ];

    public const MODES = ['per_aspect' => 'Per aspek', 'single' => 'Satu hasil pemeriksaan'];

    public const STATUSES = ['in_progress' => 'Dalam pemeriksaan', 'awaiting_response' => 'Menunggu tanggapan', 'recheck' => 'Pemeriksaan ulang', 'completed' => 'Selesai diperiksa'];

    protected function casts(): array
    {
        return [
            'comparison_price' => 'decimal:2',
            'recommended_price' => 'decimal:2',
        ];
    }
}
