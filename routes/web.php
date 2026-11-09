<?php

use App\Http\Controllers\CetakController;
use Illuminate\Support\Facades\Route;

// Root langsung mengarah ke panel (tidak ada landing page publik).
Route::redirect('/', '/admin');

// Fitur Cetak Dokumen Resmi & Rekapitulasi Warga
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/keluarga/{keluarga}/cetak', [CetakController::class, 'keluarga'])->name('keluarga.cetak');
    Route::get('/wilayah/{wilayah}/cetak-warga', [CetakController::class, 'rekapWarga'])->name('wilayah.cetak-warga');
});
