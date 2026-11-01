<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\CarbonInterface;

class SubmissionPeriod extends Model
{
    public const DISPLAY_TIMEZONE = 'Asia/Jakarta';

    protected function casts(): array
    {
        return ['budget_year' => 'integer', 'opens_at' => 'immutable_datetime', 'closes_at' => 'immutable_datetime'];
    }

    public function availability(?CarbonInterface $at = null): string
    {
        $at ??= now();
        if ($this->status === 'draft') { return 'Draf'; }
        if ($this->status === 'closed') { return 'Ditutup'; }
        if ($at->lt($this->opens_at)) { return 'Belum dibuka'; }
        // SQL timestamps have whole-second precision; accept the entire final second.
        if ($at->gte($this->closes_at->addSecond())) { return 'Berakhir'; }

        return 'Terbuka';
    }

    public function acceptsSubmissions(?CarbonInterface $at = null): bool
    {
        return $this->availability($at) === 'Terbuka';
    }
}
