<?php

use App\Http\Controllers\ZiController;
use App\Http\Controllers\ZiTemplateController;
use Illuminate\Support\Facades\Route;

Route::prefix('penilaian-zi/template')->name('zi.template.')->controller(ZiTemplateController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/{instrument}', 'show')->whereNumber('instrument')->name('show');
    Route::get('/{instrument}/butir/{item}', 'item')->whereNumber(['instrument','item'])->name('item');
    Route::post('/{instrument}/ubah', 'update')->whereNumber('instrument')->name('update');
    Route::post('/{instrument}/terbit', 'publish')->whereNumber('instrument')->name('publish');
    Route::post('/{instrument}/hapus', 'destroy')->whereNumber('instrument')->name('destroy');
});

Route::prefix('penilaian-zi')->name('zi.')->controller(ZiController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::get('/{assessment}', 'show')->whereNumber('assessment')->name('show');
    Route::post('/{assessment}/ajukan', 'submit')->whereNumber('assessment')->name('submit');
    Route::post('/{assessment}/final', 'finalize')->whereNumber('assessment')->name('finalize');
    Route::get('/{assessment}/butir/{item}', 'item')->whereNumber(['assessment','item'])->name('item');
    Route::post('/{assessment}/butir/{item}', 'save')->whereNumber(['assessment','item'])->name('save');
    Route::get('/{assessment}/bukti/{entry}', 'download')->whereNumber(['assessment','entry'])->name('evidence');
});
