<?php

return [
    // Effective only in APP_ENV=local; never relax validation in staging/production.
    'allow_missing_evidence' => env('DEMO_ALLOW_MISSING_EVIDENCE', false),
];
