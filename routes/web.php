<?php

use App\Http\Controllers\CetakController;
use App\Models\NotifikasiValidasi;
use Illuminate\Support\Facades\Route;

// Root langsung mengarah ke panel (tidak ada landing page publik).
Route::redirect('/', '/admin');

// Fitur Cetak Dokumen Resmi, Rekapitulasi Warga, & Notifikasi Validasi
Route::middleware(['auth'])->prefix('admin')->group(function () {
    Route::get('/keluarga/{keluarga}/cetak', [CetakController::class, 'keluarga'])->name('keluarga.cetak');
    Route::get('/wilayah/{wilayah}/cetak-warga', [CetakController::class, 'rekapWarga'])->name('wilayah.cetak-warga');

    Route::get('/notifikasi-validasi/{notifikasi}/buka', function (NotifikasiValidasi $notifikasi) {
        abort_unless((int) $notifikasi->id_pengguna === (int) auth()->id(), 403);
        $notifikasi->tandaiDibaca();

        return redirect($notifikasi->tautan ?: '/admin/validasi-perubahan');
    })->name('notifikasi-validasi.buka');

    Route::post('/notifikasi-validasi/baca-semua', function () {
        NotifikasiValidasi::query()
            ->where('id_pengguna', auth()->id())
            ->whereNull('dibaca_pada')
            ->update(['dibaca_pada' => now()]);

        return back();
    })->name('notifikasi-validasi.baca-semua');
});
