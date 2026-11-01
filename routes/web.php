<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionController;
use App\Http\Middleware\RequireActiveSession;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/prototype/operator', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

    return response()->view('prototype.operator')->header('Cache-Control', 'no-store');
})->name('prototype.operator');

Route::prefix('auth')->group(function (): void {
    Route::get('/csrf-token', [SessionController::class, 'csrfToken'])->middleware('throttle:60,1');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
    Route::middleware(RequireActiveSession::class)->group(function (): void {
        Route::get('/me', [SessionController::class, 'show']);
        Route::post('/logout', [SessionController::class, 'destroy']);
    });
});
