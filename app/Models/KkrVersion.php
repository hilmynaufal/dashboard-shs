<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KkrVersion extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }
}
