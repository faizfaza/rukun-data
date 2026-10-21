<?php

namespace Tests\Feature;

use App\Filament\Pages\JelajahWilayah;
use App\Filament\Resources\AnggotaResource;
use App\Filament\Resources\KeluargaResource;
use App\Models\Wilayah;
use App\Support\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

// Titik awal per peran, pemilih bertahap (daftar + cari), data keluarga baru
// tampil di level RT, tautan langsung, tombol tambah, dan pencarian persis.
//
// Halaman dibuka dengan parameter node awal eksplisit (= wilayah pengguna
// sendiri) agar harness Livewire punya parameter mount sekaligus memastikan
// rantai mulai dari keadaan kosong.
class JelajahWilayahTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $w
     */
    private function buka(array $w): Testable
    {
        return Livewire::test(JelajahWilayah::class, ['wilayah' => $w['negara']]);
    }

    /**
     * @param  array<string, mixed>  $w
     */
    private function bukaPada(Wilayah $node): Testable
    {
        return Livewire::test(JelajahWilayah::class, ['wilayah' => $node]);
    }

    /**
     * @param  list<int>  $ids
     */
    private function pilihJalur(Testable $c, array $ids): void
    {
        foreach (array_values($ids) as $indeks => $id) {
            $c->call('pilih', $indeks, $id);
        }
    }

    /**
     * @return list<string>
     */
    private function namaOpsi(Testable $c): array
    {
        return $c->instance()->opsiSaatIni()->pluck('nama')->values()->all();
    }

    public function test_langkah_pertama_superadmin_adalah_provinsi(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('sa', 'superadmin', null));

        $c = $this->buka($w);

        $this->assertSame(['Provinsi Uji'], $this->namaOpsi($c));
        $this->assertSame(0, $c->instance()->langkahSaatIni()['indeks']);
        $this->assertSame('Provinsi', $c->instance()->langkahSaatIni()['label']);
        // Belum sampai RT → daftar keluarga belum tampil.
        $this->assertNull($c->instance()->nodeTerpilihRt());
    }

    public function test_admin_rw_mulai_dari_rw(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rw', 'admin_rw', $w['rw']->id));

        $c = $this->bukaPada($w['rw']);

        $this->assertSame(['RT 001', 'RT 002'], $this->namaOpsi($c));
        $this->assertNull($c->instance()->nodeTerpilihRt());
    }

    public function test_admin_rt_tanpa_langkah_dan_langsung_siap(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        $c = $this->bukaPada($w['rt1']);

        $this->assertNull($c->instance()->langkahSaatIni());
        $this->assertSame($w['rt1']->id, $c->instance()->nodeTerpilihRt()->id);
    }

    public function test_rantai_berlanjut_tiap_opsi_dipilih(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('sa', 'superadmin', null));

        $c = $this->buka($w);

        $c->call('pilih', 0, $w['provinsi']->id);
        $this->assertSame(['Kabupaten Uji'], $this->namaOpsi($c));
        $this->assertSame(1, $c->instance()->langkahSaatIni()['indeks']);

        $c->call('pilih', 1, $w['kabupaten']->id);
        $this->assertSame(['Kecamatan Uji'], $this->namaOpsi($c));

        $c->call('pilih', 2, $w['kecamatan']->id);
        $this->assertSame(['Desa Uji'], $this->namaOpsi($c));

        $c->call('pilih', 3, $w['desa']->id);
        $this->assertSame(['RW 001'], $this->namaOpsi($c));
    }

    public function test_kotak_cari_menyaring_opsi(): void
    {
        $w = $this->seedDasar();

        // Desa kedua di kecamatan yang sama, agar ada dua opsi.
        Wilayah::create([
            'kode' => 'T.1.1.1.2', 'nama' => 'Desa Lain', 'level' => 'desa', 'tipe' => 'desa', 'parent_id' => $w['kecamatan']->id,
        ]);

        $this->actingAs($this->makeUser('sa', 'superadmin', null));

        $c = $this->buka($w);
        $this->pilihJalur($c, [$w['provinsi']->id, $w['kabupaten']->id, $w['kecamatan']->id]);

        $this->assertSame(['Desa Lain', 'Desa Uji'], $this->namaOpsi($c));

        $c->set('cari', 'lain');
        $this->assertSame(['Desa Lain'], $this->namaOpsi($c));
    }

    public function test_kembali_ke_level_atas_menghapus_pilihan_dalam(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('sa', 'superadmin', null));

        $c = $this->buka($w);
        $this->pilihJalur($c, [$w['provinsi']->id, $w['kabupaten']->id, $w['kecamatan']->id]);

        $this->assertSame(3, $c->instance()->langkahSaatIni()['indeks']);

        // Kembali ke provinsi (posisi 1 pada rantai).
        $c->call('kembaliKe', 1);

        $this->assertSame(1, $c->instance()->langkahSaatIni()['indeks']);
        $this->assertSame(['Kabupaten Uji'], $this->namaOpsi($c));
    }

    public function test_data_keluarga_baru_tampil_setelah_sampai_rt(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $sa->id);

        $this->actingAs($sa);

        $masked = SensitiveData::mask('6371010101000001');

        $c = $this->buka($w);
        $c->assertDontSee($masked, false);

        $this->pilihJalur($c, [
            $w['provinsi']->id, $w['kabupaten']->id, $w['kecamatan']->id,
            $w['desa']->id, $w['rw']->id, $w['rt1']->id,
        ]);

        $this->assertSame($w['rt1']->id, $c->instance()->nodeTerpilihRt()->id);
        $c->assertSee($masked, false);
    }

    public function test_tautan_langsung_mengisi_rantai(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $sa->id);
        $adminRw = $this->makeUser('rw', 'admin_rw', $w['rw']->id);

        $this->actingAs($adminRw);

        $this->get('/admin/jelajah/'.$w['rt1']->id)
            ->assertOk()
            ->assertSee(SensitiveData::mask('6371010101000001'), false);
    }

    public function test_tautan_di_luar_subtree_404(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        $this->get('/admin/jelajah/'.$w['rt2']->id)->assertNotFound();
        $this->get('/admin/jelajah/'.$w['desa']->id)->assertNotFound();
    }

    public function test_tombol_tambah_untuk_admin_rt(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        $this->assertTrue($this->bukaPada($w['rt1'])->instance()->bolehTambahKeluarga());
        $this->get('/admin/jelajah')->assertOk()->assertSee('Tambah keluarga');
    }

    public function test_tombol_tambah_tidak_untuk_admin_rw(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('rw', 'admin_rw', $w['rw']->id));

        $c = $this->bukaPada($w['rw']);
        $this->assertFalse($c->instance()->bolehTambahKeluarga());

        // Bahkan setelah sampai RT, admin_rw tetap tidak boleh menambah.
        $c->call('pilih', 0, $w['rt1']->id);
        $this->assertFalse($c->instance()->bolehTambahKeluarga());
    }

    public function test_tombol_tambah_untuk_superadmin_setelah_sampai_rt(): void
    {
        $w = $this->seedDasar();
        $this->actingAs($this->makeUser('sa', 'superadmin', null));

        $c = $this->buka($w);
        $this->assertFalse($c->instance()->bolehTambahKeluarga());

        $this->pilihJalur($c, [
            $w['provinsi']->id, $w['kabupaten']->id, $w['kecamatan']->id,
            $w['desa']->id, $w['rw']->id, $w['rt1']->id,
        ]);

        $this->assertTrue($c->instance()->bolehTambahKeluarga());
        $this->assertSame($w['rt1']->id, $c->instance()->nodeTerpilihRt()->id);
    }

    public function test_pencarian_no_kk_persis(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $sa->id);

        $this->actingAs($sa);

        $this->buka($w)
            ->call('cariPersis', '6371010101000001')
            ->assertRedirect(KeluargaResource::getUrl('view', ['record' => $keluarga]));
    }

    public function test_pencarian_nik_persis(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $sa->id);
        $anggota = $keluarga->anggota()->first();

        $this->actingAs($sa);

        $this->buka($w)
            ->call('cariPersis', '6371010101800001')
            ->assertRedirect(AnggotaResource::getUrl('view', ['record' => $anggota]));
    }

    public function test_pencarian_menghormati_scope_wilayah(): void
    {
        $w = $this->seedDasar();
        $sa = $this->makeUser('sa', 'superadmin', null);
        $this->makeKeluarga($w['rt2']->id, '2222222222222222', [
            ['nik' => '2222222222222222', 'hubungan' => 1],
        ], $sa->id);

        $this->actingAs($this->makeUser('rt', 'admin_rt', $w['rt1']->id));

        $this->bukaPada($w['rt1'])
            ->call('cariPersis', '2222222222222222')
            ->assertNoRedirect();
    }
}
