<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposalItem extends Model
{
    public const LABELS = [
        'draft' => 'Draf', 'submitted' => 'Menunggu verifikasi',
        'needs_completion' => 'Perlu dilengkapi', 'ready' => 'Siap direviu',
        'assigned' => 'Ditugaskan', 'reviewing' => 'Dalam reviu',
        'awaiting_response' => 'Menunggu tanggapan', 'recheck' => 'Pemeriksaan ulang',
        'reviewed' => 'Selesai direviu',
    ];
}
