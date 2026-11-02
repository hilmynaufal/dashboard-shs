<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KkrDocument extends Model
{
    public const LABELS = ['draft'=>'Draf','awaiting_lead'=>'Menunggu Ketua Tim','returned'=>'Dikembalikan','awaiting_controller'=>'Menunggu Pengendali Teknis','final'=>'Final','superseded'=>'Digantikan'];
}
