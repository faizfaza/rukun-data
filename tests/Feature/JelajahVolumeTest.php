<?php

namespace Tests\Feature;

use App\Filament\Pages\JelajahWilayah;
use App\Models\Keluarga;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

// Fase 3.8/3.10: jumlah query agregasi halaman Jelajah harus konstan walau
// jumlah anak/KK bertambah (bukti tidak ada N+1).
class JelajahVolumeTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_jumlah_query_agregasi_konstan(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($sa);

        // Tahap 1: 5 RW tambahan (masing-masing 1 RT berisi 3 KK).
        $this->buatRw($w['desa'], 5);
        $c1 = Livewire::test(JelajahWilayah::class, ['wilayah' => $w['desa']]);
        $q1 = $this->hitungQuery(fn (): array => $c1->instance()->kkKeturunan());
        $n1 = count($c1->instance()->kkKeturunan());

        // Tahap 2: 25 RW tambahan lagi → data jauh bertambah.
        $this->buatRw($w['desa'], 25);
        $c2 = Livewire::test(JelajahWilayah::class, ['wilayah' => $w['desa']]);
        $q2 = $this->hitungQuery(fn (): array => $c2->instance()->kkKeturunan());
        $n2 = count($c2->instance()->kkKeturunan());

        $this->assertGreaterThan($n1, $n2, 'Data anak harus bertambah.');
        $this->assertSame($q1, $q2, 'Jumlah query harus tetap walau data bertambah.');
        $this->assertLessThanOrEqual(5, $q2);
    }

    public function test_ringkasan_dan_tabel_anak_tanpa_n_plus_1(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $this->actingAs($sa);

        $this->buatRw($w['desa'], 30);

        $c = Livewire::test(JelajahWilayah::class, ['wilayah' => $w['desa']]);

        $q = $this->hitungQuery(function () use ($c): void {
            $c->instance()->ringkasan();
        });

        $this->assertLessThanOrEqual(4, $q);
    }

    private function buatRw(Wilayah $desa, int $jumlah): void
    {
        $urutAwal = Wilayah::where('parent_id', $desa->id)->where('level', 'rw')->count();

        for ($i = 1; $i <= $jumlah; $i++) {
            $urut = $urutAwal + $i;

            $rw = Wilayah::create([
                'kode' => 'VOL-RW-'.$urut,
                'nama' => 'RW Vol '.$urut,
                'level' => 'rw',
                'parent_id' => $desa->id,
            ]);

            $rt = Wilayah::create([
                'kode' => 'VOL-RT-'.$urut,
                'nama' => 'RT Vol '.$urut,
                'level' => 'rt',
                'parent_id' => $rw->id,
            ]);

            for ($k = 0; $k < 3; $k++) {
                $nomor = sprintf('%s%03d', $urut, $k);

                Keluarga::create([
                    'id_wilayah' => $rt->id,
                    'no_kk' => '8881'.str_pad((string) $nomor, 12, '0', STR_PAD_LEFT),
                    'alamat' => 'Jl. Volume '.$nomor,
                    'kode_pos' => '40362',
                ]);
            }
        }
    }

    private function hitungQuery(callable $callback): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }
}
