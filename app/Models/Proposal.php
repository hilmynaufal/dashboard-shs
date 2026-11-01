<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proposal extends Model
{
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }
}
