<?php

namespace Database\Seeders;

use App\Models\Anggota;
use App\Models\EkonomiKeluarga;
use App\Models\Keluarga;
use App\Models\RefAgama;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\Wilayah;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

// Fase 3.8: uji volume. BUKAN bagian DatabaseSeeder; dijalankan manual.
// Membuat sekitar 2000 KK dan 8000 anggota tersebar di banyak RT untuk
// memeriksa halaman Jelajah tetap responsif dan jumlah query konstan (tanpa N+1).
//
//   php82.bat artisan migrate:fresh --seed
//   php82.bat artisan db:seed --class=VolumeDummySeeder
class VolumeDummySeeder extends Seeder
{
    public int $jumlahKeluarga = 2000;

    public int $anggotaPerKeluarga = 4;

    public int $jumlahRt = 20;

    public function run(): void
    {
        $desa = Wilayah::where('level', 'desa')->orderBy('path')->first();

        if ($desa === null) {
            $this->command?->error('Jalankan dulu DatabaseSeeder (butuh pohon wilayah contoh).');

            return;
        }

        $refs = [
            'agama' => RefAgama::pluck('id')->all(),
            'pendidikan' => RefPendidikan::pluck('id')->all(),
            'pekerjaan' => RefPekerjaan::pluck('id')->all(),
        ];

        if ($refs['agama'] === [] || $refs['pendidikan'] === [] || $refs['pekerjaan'] === []) {
            $this->command?->error('Tabel referensi kosong. Jalankan dulu DatabaseSeeder.');

            return;
        }

        DB::disableQueryLog();

        $rts = $this->buatRt($desa);
        $this->buatKeluarga($rts, $refs);
    }

    /**
     * @return list<Wilayah>
     */
    private function buatRt(Wilayah $desa): array
    {
        $rws = Wilayah::where('level', 'rw')
            ->where('path', 'like', $desa->path.'%')
            ->orderBy('path')
            ->get();

        if ($rws->isEmpty()) {
            $rws = collect([
                Wilayah::create(['kode' => 'RW-VOL', 'nama' => 'RW Vol', 'level' => 'rw', 'parent_id' => $desa->id]),
            ]);
        }

        $rts = [];

        for ($i = 1; $i <= $this->jumlahRt; $i++) {
            $nomor = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $rw = $rws[($i - 1) % $rws->count()];

            $rts[] = Wilayah::firstOrCreate(
                ['parent_id' => $rw->id, 'kode' => 'VOL-'.$nomor],
                ['nama' => 'RT Vol '.$nomor, 'level' => 'rt'],
            );
        }

        return $rts;
    }

    /**
     * @param  list<Wilayah>  $rts
     * @param  array<string, list<int>>  $refs
     */
    private function buatKeluarga(array $rts, array $refs): void
    {
        $hubunganKepala = RefHubungan::KEPALA;
        $hubunganLain = RefHubungan::query()->whereKeyNot(RefHubungan::KEPALA)->value('id') ?? 3;
        $jumlahRt = count($rts);

        for ($i = 0; $i < $this->jumlahKeluarga; $i++) {
            $rt = $rts[$i % $jumlahRt];
            $noKk = '9991'.str_pad((string) $i, 12, '0', STR_PAD_LEFT);

            DB::transaction(function () use ($i, $rt, $noKk, $refs, $hubunganKepala, $hubunganLain): void {
                $keluarga = Keluarga::create([
                    'id_wilayah' => $rt->id,
                    'no_kk' => $noKk,
                    'alamat' => 'Jl. Volume No. '.($i + 1),
                    'kode_pos' => '40362',
                ]);

                for ($a = 0; $a < $this->anggotaPerKeluarga; $a++) {
                    $urut = $i * $this->anggotaPerKeluarga + $a;

                    $anggota = Anggota::create([
                        'id_keluarga' => $keluarga->id,
                        'nik' => '9990'.str_pad((string) $urut, 12, '0', STR_PAD_LEFT),
                        'nama_lengkap' => 'Warga Volume '.$urut,
                        'tempat_lahir' => 'Bandung',
                        'tanggal_lahir' => '1980-01-01',
                        'jenis_kelamin' => $a % 2 === 0 ? 'L' : 'P',
                        'status_kawin' => $a === 0 ? 'kawin' : 'belum_kawin',
                        'id_agama' => $refs['agama'][$urut % count($refs['agama'])],
                        'id_pendidikan' => $refs['pendidikan'][$urut % count($refs['pendidikan'])],
                        'id_pekerjaan' => $refs['pekerjaan'][$urut % count($refs['pekerjaan'])],
                        'id_hubungan' => $a === 0 ? $hubunganKepala : $hubunganLain,
                    ]);

                    if ($a === 0) {
                        $anggota->forceFill(['kepala_lock' => $keluarga->id])->saveQuietly();
                    }
                }

                EkonomiKeluarga::create([
                    'id_keluarga' => $keluarga->id,
                    'tahun_data' => 2026,
                    'kategori_ekonomi' => 'Menengah',
                ]);
            });
        }
    }
}
