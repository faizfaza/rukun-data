<?php

namespace Tests\Feature;

use App\Filament\Pages\JelajahWilayah;
use App\Models\Keluarga;
use App\Models\Wilayah;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

// Uji volume pemilih bertahap: jumlah query untuk menyusun satu langkah tetap
// walau jumlah wilayah dan keluarga bertambah (tanpa N+1), dan pemilih tidak
// pernah menyentuh tabel keluarga/anggota.
class JelajahVolumeTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_jumlah_query_langkah_konstan(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('sa', 'superadmin', null));

        $this->buatRw($w['desa'], 5);
        $q1 = $this->queryLangkahDesa($w);

        $this->buatRw($w['desa'], 25);
        $q2 = $this->queryLangkahDesa($w);

        $this->assertSame($q1, $q2, 'Jumlah query harus tetap walau wilayah bertambah.');
        $this->assertLessThanOrEqual(8, $q2);
    }

    public function test_pemilih_tidak_menyentuh_tabel_keluarga(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('sa', 'superadmin', null));
        $this->buatRw($w['desa'], 3);

        $c = $this->keDesa($w);

        $sql = $this->sqlLangkah(fn () => $c->instance()->opsiSaatIni());

        $this->assertStringNotContainsString('keluarga', $sql);
        $this->assertStringNotContainsString('anggota', $sql);
    }

    /**
     * @param  array<string, Wilayah>  $w
     */
    private function keDesa(array $w): Testable
    {
        $c = Livewire::test(JelajahWilayah::class, ['wilayah' => $w['negara']]);

        foreach ([$w['provinsi']->id, $w['kabupaten']->id, $w['kecamatan']->id, $w['desa']->id] as $indeks => $id) {
            $c->call('pilih', $indeks, $id);
        }

        return $c;
    }

    /**
     * @param  array<string, Wilayah>  $w
     */
    private function queryLangkahDesa(array $w): int
    {
        $c = $this->keDesa($w);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $c->instance()->opsiSaatIni();

        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $jumlah;
    }

    private function sqlLangkah(callable $callback): string
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $callback();

        $sql = strtolower(implode(' ', array_map(fn (array $q): string => $q['query'], DB::getQueryLog())));
        DB::disableQueryLog();

        return $sql;
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
}
