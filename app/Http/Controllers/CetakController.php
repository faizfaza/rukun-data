<?php

namespace App\Http\Controllers;

use App\Models\Keluarga;
use App\Models\Wilayah;
use App\Support\WilayahAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CetakController extends Controller
{
    /**
     * Cetak dokumen resmi Kartu Keluarga (KK).
     */
    public function keluarga(Keluarga $keluarga)
    {
        Gate::authorize('view', $keluarga);

        $keluarga->load([
            'wilayah',
            'kepala',
            'anggota' => fn ($q) => $q->orderBy('id_hubungan', 'asc'),
            'anggota.hubungan',
            'anggota.agama',
            'anggota.pendidikan',
            'anggota.pekerjaan',
            'aset',
        ]);

        return view('cetak.keluarga', compact('keluarga'));
    }

    /**
     * Cetak rekapitulasi data warga RT atau RW.
     */
    public function rekapWarga(Request $request, Wilayah $wilayah)
    {
        $allowedIds = WilayahAccess::allowedRtIds(auth()->user());

        if ($allowedIds !== null) {
            $rtIds = $wilayah->level === 'rt'
                ? [$wilayah->id]
                : $wilayah->descendants()->where('level', 'rt')->pluck('id')->all();

            $hasAccess = count(array_intersect($rtIds, $allowedIds)) > 0;
            abort_unless($hasAccess, 403, 'Anda tidak memiliki akses ke wilayah ini.');
        }

        $query = Keluarga::query()
            ->with([
                'wilayah',
                'kepala',
                'anggota' => fn ($q) => $q->orderBy('id_hubungan', 'asc'),
            ]);

        if ($wilayah->level === 'rt') {
            $query->where('id_wilayah', $wilayah->id);
        } else {
            $rtIds = $wilayah->descendants()->where('level', 'rt')->pluck('id')->all();
            $query->whereIn('id_wilayah', $rtIds);
        }

        $keluargaList = $query->get();

        $totalKeluarga = $keluargaList->count();
        $totalWarga = 0;
        $totalMenetap = 0;
        $totalPindah = 0;
        $totalMeninggal = 0;

        foreach ($keluargaList as $k) {
            foreach ($k->anggota as $a) {
                $totalWarga++;
                if ($a->status_kependudukan === 'pindah') {
                    $totalPindah++;
                } elseif ($a->status_kependudukan === 'meninggal') {
                    $totalMeninggal++;
                } else {
                    $totalMenetap++;
                }
            }
        }

        $statistik = [
            'total_keluarga' => $totalKeluarga,
            'total_warga' => $totalWarga,
            'total_menetap' => $totalMenetap,
            'total_pindah' => $totalPindah,
            'total_meninggal' => $totalMeninggal,
        ];

        return view('cetak.rekap-warga', compact('wilayah', 'keluargaList', 'statistik'));
    }
}

