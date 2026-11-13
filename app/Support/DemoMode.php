<?php

namespace App\Support;

final class DemoMode
{
    public static function allowsMissingEvidence(): bool
    {
        return app()->environment('local') && config('demo.allow_missing_evidence') === true;
    }
}
