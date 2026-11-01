<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionController;
use App\Http\Middleware\RequireActiveSession;
use App\Http\Controllers\{AccountAccessController, AccountController, ProfileController, WorkspaceController};

Route::get('/', function () {
    return view('welcome');
});

Route::get('/masuk', [SessionController::class, 'create'])->name('login')->middleware('cache.headers:no_store');

Route::middleware(RequireActiveSession::class)->group(function (): void {
    Route::get('/aplikasi', [WorkspaceController::class, 'show'])->name('workspace');
    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profil/kata-sandi', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');
    Route::get('/admin/akun', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/admin/akun/buat', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/admin/akun', [AccountController::class, 'store'])->name('accounts.store');
    Route::patch('/admin/akun/{account}/status', [AccountController::class, 'status'])->name('accounts.status');
    Route::get('/admin/akun/{account}/akses', [AccountAccessController::class, 'edit'])->name('accounts.access.edit');
    Route::patch('/admin/akun/{account}/akses', [AccountAccessController::class, 'update'])->name('accounts.access.update');
});

Route::get('/prototype/operator', function () {
    abort_unless(app()->environment(['local', 'testing']), 404);

    return response()->view('prototype.operator')->header('Cache-Control', 'no-store');
})->name('prototype.operator');

Route::prefix('auth')->group(function (): void {
    Route::get('/csrf-token', [SessionController::class, 'csrfToken'])->middleware('throttle:60,1');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login')->name('session.store');
    Route::middleware(RequireActiveSession::class)->group(function (): void {
        Route::get('/me', [SessionController::class, 'show']);
        Route::post('/logout', [SessionController::class, 'destroy'])->name('session.destroy');
    });
});
