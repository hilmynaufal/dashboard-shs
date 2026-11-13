<?php

use App\Http\Controllers\InspectorateImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('impor-inspektorat')->name('inspectorate.imports.')->controller(InspectorateImportController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'upload')->middleware('throttle:5,1')->name('upload');
    Route::get('/{import}', 'show')->whereNumber('import')->name('show');
    Route::post('/{import}', 'update')->whereNumber('import')->name('update');
    Route::post('/{import}/batal', 'cancel')->whereNumber('import')->name('cancel');
});
