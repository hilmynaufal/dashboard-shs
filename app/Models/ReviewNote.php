<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewNote extends Model
{
    public const LABELS = ['internal' => 'Internal tim', 'awaiting_lead' => 'Menunggu Ketua Tim', 'sent' => 'Menunggu tanggapan SKPD', 'responded' => 'Menunggu pemeriksaan tanggapan', 'resolved' => 'Selesai ditindaklanjuti', 'open_at_final' => 'Terbuka pada final'];
}
