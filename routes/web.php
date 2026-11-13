<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewNoteController;
use App\Http\Controllers\ProposalHistoryController;
use App\Http\Controllers\KkrController;
use App\Http\Controllers\ProgressController;
use App\Http\Controllers\ItemReportController;
use App\Http\Controllers\{KkrArchiveController,ActivityController};
use App\Http\Middleware\RequireActiveSession;
use App\Http\Controllers\{AccountAccessController, AccountController, PeriodController, ProfileController, ProposalController, ProposalEvidenceController, ProposalImportController, ReferenceController, VerificationController, WorkspaceController};
use App\Support\ReferenceCatalog;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/masuk', [SessionController::class, 'create'])->name('login')->middleware('cache.headers:no_store');

Route::middleware(RequireActiveSession::class)->group(function (): void {
    require __DIR__.'/zi.php';
    Route::get('/aplikasi', [WorkspaceController::class, 'show'])->name('workspace');
    Route::get('/pemantauan',[ProgressController::class,'index'])->name('progress.index');
    Route::get('/rekap',[ItemReportController::class,'index'])->name('reports.index');
    Route::get('/rekap/excel',[ItemReportController::class,'export'])->name('reports.export');
    Route::get('/arsip-kkr',[KkrArchiveController::class,'index'])->name('archives.index');
    Route::get('/arsip-kkr/{version}',[KkrArchiveController::class,'show'])->whereNumber('version')->name('archives.show');
    Route::get('/aktivitas',[ActivityController::class,'index'])->name('activity.index');
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
    Route::get('/usulan',[ProposalController::class,'index'])->name('proposals.index');
    Route::get('/kkr',[KkrController::class,'index'])->name('kkr.index');
    Route::get('/penugasan/{assignment}/kkr/buat',[KkrController::class,'create'])->whereNumber('assignment')->name('kkr.create');
    Route::post('/penugasan/{assignment}/kkr',[KkrController::class,'store'])->whereNumber('assignment')->name('kkr.store');
    Route::get('/kkr/{document}',[KkrController::class,'show'])->whereNumber('document')->name('kkr.show');
    Route::get('/kkr/{document}/ubah',[KkrController::class,'edit'])->whereNumber('document')->name('kkr.edit');
    Route::get('/kkr/{document}/koreksi',[KkrController::class,'correction'])->whereNumber('document')->name('kkr.correction');
    Route::post('/kkr/{document}/koreksi',[KkrController::class,'correct'])->whereNumber('document')->name('kkr.correct');
    Route::get('/kkr/{document}/versi/{version}/preview',[KkrController::class,'preview'])->whereNumber(['document','version'])->name('kkr.preview');
    Route::get('/kkr/{document}/versi/{version}/word',[KkrController::class,'download'])->whereNumber(['document','version'])->name('kkr.word');
    Route::post('/kkr/{document}/versi',[KkrController::class,'revise'])->whereNumber('document')->name('kkr.revise');
    Route::post('/kkr/{document}/ajukan',[KkrController::class,'submit'])->whereNumber('document')->name('kkr.submit');
    Route::post('/kkr/{document}/versi/{version}/keputusan',[KkrController::class,'decide'])->whereNumber(['document','version'])->name('kkr.decide');
    Route::get('/catatan', [ReviewNoteController::class, 'index'])->name('notes.index');
    Route::get('/catatan/{note}', [ReviewNoteController::class, 'show'])->whereNumber('note')->name('notes.show');
    Route::post('/catatan/{note}/kirim', [ReviewNoteController::class, 'send'])->whereNumber('note')->name('notes.send');
    Route::post('/catatan/{note}/tanggapan', [ReviewNoteController::class, 'respond'])->whereNumber('note')->name('notes.respond');
    Route::post('/catatan/{note}/keputusan', [ReviewNoteController::class, 'decide'])->whereNumber('note')->name('notes.decide');
    Route::post('/verifikasi/{item}/catatan', [ReviewNoteController::class, 'administrative'])->whereNumber('item')->name('notes.administrative');
    Route::get('/verifikasi', [VerificationController::class, 'index'])->name('verification.index');
    Route::get('/penugasan', [AssignmentController::class, 'index'])->name('assignments.index');
    Route::get('/penugasan/buat', [AssignmentController::class, 'create'])->name('assignments.create');
    Route::post('/penugasan', [AssignmentController::class, 'store'])->name('assignments.store');
    Route::get('/penugasan/{assignment}', [AssignmentController::class, 'show'])->whereNumber('assignment')->name('assignments.show');
    Route::get('/penugasan/{assignment}/pilih-item', [AssignmentController::class, 'candidates'])->whereNumber('assignment')->name('assignments.candidates');
    Route::post('/penugasan/{assignment}/item', [AssignmentController::class, 'allocate'])->whereNumber('assignment')->name('assignments.allocate');
    Route::post('/penugasan/{assignment}/item/{scope}/alihkan', [AssignmentController::class, 'transfer'])->whereNumber(['assignment', 'scope'])->name('assignments.transfer');
    Route::get('/penugasan/{assignment}/item/{scope}/alihkan', [AssignmentController::class, 'transferForm'])->whereNumber(['assignment', 'scope'])->name('assignments.transfer.form');
    Route::get('/pekerjaan', [AssignmentController::class, 'work'])->name('assignments.work');
    Route::get('/penugasan/{assignment}/item/{scope}/reviu', [ReviewController::class, 'desk'])->whereNumber(['assignment', 'scope'])->name('reviews.desk');
    Route::post('/penugasan/{assignment}/item/{scope}/reviu', [ReviewController::class, 'start'])->whereNumber(['assignment', 'scope'])->name('reviews.start');
    Route::get('/reviu/{review}', [ReviewController::class, 'show'])->whereNumber('review')->name('reviews.show');
    Route::post('/reviu/{review}', [ReviewController::class, 'save'])->whereNumber('review')->name('reviews.save');
    Route::post('/reviu/{review}/catatan', [ReviewController::class, 'note'])->whereNumber('review')->name('reviews.note');
    Route::get('/verifikasi/{item}', [VerificationController::class, 'show'])->whereNumber('item')->name('verification.show');
    Route::post('/verifikasi/{item}', [VerificationController::class, 'decide'])->whereNumber('item')->name('verification.decide');
    Route::get('/usulan/buat',[ProposalController::class,'create'])->name('proposals.create');
    Route::post('/usulan',[ProposalController::class,'store'])->name('proposals.store');
    Route::get('/usulan/referensi/{kind}',[ProposalController::class,'references'])->whereIn('kind',array_keys(ReferenceCatalog::TYPES))->name('proposals.references');
    Route::prefix('usulan/{proposal}')->whereNumber('proposal')->group(function():void{
        Route::get('/item/{item}/riwayat', [ProposalHistoryController::class, 'show'])->whereNumber('item')->name('proposals.items.history');
        Route::get('/',[ProposalController::class,'show'])->name('proposals.show');
        Route::get('/item/buat',[ProposalController::class,'itemForm'])->name('proposals.items.create');
        Route::post('/item',[ProposalController::class,'saveItem'])->name('proposals.items.store');
        Route::get('/item/{item}/ubah',[ProposalController::class,'itemForm'])->whereNumber('item')->name('proposals.items.edit');
        Route::patch('/item/{item}',[ProposalController::class,'saveItem'])->whereNumber('item')->name('proposals.items.update');
        Route::post('/item/{item}/kelengkapan', [VerificationController::class, 'resubmit'])->whereNumber('item')->name('proposals.items.resubmit');
        Route::get('/item/{item}/kelengkapan', [VerificationController::class, 'completion'])->whereNumber('item')->name('proposals.items.completion');
        Route::get('/item/{item}/versi/{version}',[ProposalController::class,'version'])->whereNumber(['item','version'])->name('proposals.items.version');
        Route::post('/item/{item}/bukti',[ProposalEvidenceController::class,'store'])->whereNumber('item')->name('proposals.evidence.store');
        Route::delete('/item/{item}/bukti/{file}',[ProposalEvidenceController::class,'remove'])->whereNumber(['item','file'])->name('proposals.evidence.remove');
        Route::get('/periksa',[ProposalController::class,'check'])->name('proposals.check');
        Route::post('/kirim',[ProposalController::class,'submit'])->name('proposals.submit');
        Route::get('/impor',[ProposalImportController::class,'create'])->name('proposals.import.create');
        Route::post('/impor',[ProposalImportController::class,'upload'])->middleware('throttle:10,1')->name('proposals.import.upload');
        Route::get('/impor/{batch}',[ProposalImportController::class,'show'])->whereNumber('batch')->name('proposals.import.show');
        Route::patch('/impor/{batch}/pemetaan',[ProposalImportController::class,'mapping'])->whereNumber('batch')->name('proposals.import.mapping');
        Route::get('/impor/{batch}/baris/{row}',[ProposalImportController::class,'rowForm'])->whereNumber(['batch','row'])->name('proposals.import.row');
        Route::patch('/impor/{batch}/baris/{row}',[ProposalImportController::class,'rowUpdate'])->whereNumber(['batch','row'])->name('proposals.import.row.update');
        Route::post('/impor/{batch}/simpan',[ProposalImportController::class,'commit'])->whereNumber('batch')->name('proposals.import.commit');
        Route::post('/impor/{batch}/batal',[ProposalImportController::class,'cancel'])->whereNumber('batch')->name('proposals.import.cancel');
    });
    Route::get('/bukti/{file}',[ProposalEvidenceController::class,'download'])->whereNumber('file')->name('evidence.download');
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
