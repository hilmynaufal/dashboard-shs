<?php

namespace App\Support;

use App\Models\SubmissionPeriod;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class SubmissionWindow
{
    /** Call after locking the period inside the eventual submission transaction (K-10). */
    public static function assertOpen(SubmissionPeriod $period, ?CarbonInterface $at = null): void
    {
        if (! $period->acceptsSubmissions($at)) {
            throw ValidationException::withMessages(['submission_period_id' => 'Usulan belum dapat dikirim: periode '.$period->availability($at).'.']);
        }
    }
}
