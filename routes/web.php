<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionController;
use App\Http\Middleware\RequireActiveSession;
use App\Http\Controllers\{AccountAccessController, AccountController, PeriodController, ProfileController, ReferenceController, WorkspaceController};
use App\Support\ReferenceCatalog;

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
    Route::get('/periode', [PeriodController::class, 'index'])->name('periods.index');
    Route::get('/periode/buat', [PeriodController::class, 'create'])->name('periods.create');
    Route::post('/periode', [PeriodController::class, 'store'])->name('periods.store');
    Route::get('/periode/{period}/ubah', [PeriodController::class, 'edit'])->whereNumber('period')->name('periods.edit');
    Route::patch('/periode/{period}', [PeriodController::class, 'update'])->whereNumber('period')->name('periods.update');
    Route::prefix('referensi/{kind}')->whereIn('kind', array_keys(ReferenceCatalog::TYPES))->group(function (): void {
        Route::get('/', [ReferenceController::class, 'index'])->name('references.index');
        Route::get('/buat', [ReferenceController::class, 'create'])->name('references.create');
        Route::post('/', [ReferenceController::class, 'store'])->name('references.store');
        Route::get('/{record}/ubah', [ReferenceController::class, 'edit'])->whereNumber('record')->name('references.edit');
        Route::patch('/{record}', [ReferenceController::class, 'update'])->whereNumber('record')->name('references.update');
        Route::delete('/{record}', [ReferenceController::class, 'destroy'])->whereNumber('record')->name('references.destroy');
    });
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
