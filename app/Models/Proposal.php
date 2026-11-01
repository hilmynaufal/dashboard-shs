<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    public const LABELS = ['draft'=>'Draf','submitted'=>'Diajukan','verification'=>'Verifikasi','review'=>'Dalam reviu','completed'=>'Selesai'];
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }
}
