<?php

namespace Tests\Feature;

use App\Filament\Resources\KeluargaResource\Pages\CreateKeluarga;
use App\Models\Keluarga;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// PRD §14.1: petugas RT dapat login, memasukkan satu keluarga dengan
// beberapa anggota, lalu profil tiap anggota tampil (F5 via ViewAnggota).
class AlurKeluargaTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_petugas_rt_input_satu_keluarga_utuh(): void
    {
        $w = $this->seedDasar();
        $rt1 = $this->makeUser('petugas-rt1', 'admin_rt', $w['rt1']->id);

        $this->actingAs($rt1);

        Livewire::test(CreateKeluarga::class)
            ->fillForm([
                'id_wilayah' => $w['rt1']->id,
                'no_kk' => '6371010101000001',
                'alamat' => 'Jl. Mawar No. 10',
                'kode_pos' => '70711',
                'ekonomi' => [
                    ['tahun_data' => 2026, 'kategori_ekonomi' => 'Menengah'],
                ],
                'anggota' => [
                    [
                        'nik' => '6371010101800001',
                        'nama_lengkap' => 'Budi Santoso',
                        'tempat_lahir' => 'Banjarbaru',
                        'tanggal_lahir' => '1980-01-01',
                        'jenis_kelamin' => 'L',
                        'status_kawin' => 'kawin',
                        'id_agama' => 1,
                        'id_pendidikan' => 2,
                        'id_pekerjaan' => 3,
                        'id_hubungan' => 1,
                        'nama_ayah' => 'Ahmad',
                        'nama_ibu' => 'Siti',
                        'penghasilan_bulanan' => '4500000',
                    ],
                    [
                        'nik' => '6371010101850002',
                        'nama_lengkap' => 'Dewi Lestari',
                        'tempat_lahir' => 'Banjarbaru',
                        'tanggal_lahir' => '1985-01-01',
                        'jenis_kelamin' => 'P',
                        'status_kawin' => 'kawin',
                        'id_agama' => 1,
                        'id_pendidikan' => 2,
                        'id_pekerjaan' => 1,
                        'id_hubungan' => 2,
                        'nama_ayah' => null,
                        'nama_ibu' => null,
                        'penghasilan_bulanan' => null,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $keluarga = Keluarga::first();

        $this->assertNotNull($keluarga);
        $this->assertSame('6371010101000001', $keluarga->no_kk);
        $this->assertCount(2, $keluarga->anggota);
        $this->assertSame('Budi Santoso', $keluarga->anggota->firstWhere('id_hubungan', 1)->nama_lengkap);
        $this->assertSame($rt1->id, $keluarga->created_by);
    }

    public function test_no_kk_ganda_ditolak_dengan_pesan(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);
        $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $admin->id);

        $rt1 = $this->makeUser('petugas-rt1', 'admin_rt', $w['rt1']->id);
        $this->actingAs($rt1);

        Livewire::test(CreateKeluarga::class)
            ->fillForm([
                'id_wilayah' => $w['rt1']->id,
                'no_kk' => '6371010101000001',
                'alamat' => 'Jl. Lain',
                'kode_pos' => '70711',
                'anggota' => [[
                    'nik' => '6371010101900009',
                    'nama_lengkap' => 'Orang Lain',
                    'tempat_lahir' => 'Uji',
                    'tanggal_lahir' => '1990-01-01',
                    'jenis_kelamin' => 'L',
                    'status_kawin' => 'kawin',
                    'id_agama' => 1,
                    'id_pendidikan' => 1,
                    'id_pekerjaan' => 1,
                    'id_hubungan' => 1,
                    'nama_ayah' => null,
                    'nama_ibu' => null,
                    'penghasilan_bulanan' => null,
                ]],
            ])
            ->call('create')
            ->assertHasFormErrors(['no_kk']);

        $this->assertSame(1, Keluarga::count());
    }

    public function test_superadmin_input_keluarga_dengan_cascading_wilayah(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($admin);

        Livewire::test(CreateKeluarga::class)
            ->fillForm([
                'provinsi_id' => $w['provinsi']->id,
                'kabkota_id' => $w['kabupaten']->id,
                'kecamatan_id' => $w['kecamatan']->id,
                'kelurahan_id' => $w['desa']->id,
                'rw_id' => $w['rw']->id,
                'id_wilayah' => $w['rt1']->id,
                'no_kk' => '6371010101000088',
                'alamat' => 'Jl. Cascad No. 1',
                'kode_pos' => '70711',
                'anggota' => [
                    [
                        'nik' => '6371010101800088',
                        'nama_lengkap' => 'Kepala Keluarga Cascad',
                        'tempat_lahir' => 'Bandung',
                        'tanggal_lahir' => '1985-05-05',
                        'jenis_kelamin' => 'L',
                        'status_kawin' => 'kawin',
                        'id_agama' => 1,
                        'id_pendidikan' => 3,
                        'id_pekerjaan' => 2,
                        'id_hubungan' => 1,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoErrors();

        $keluarga = Keluarga::where('id_wilayah', $w['rt1']->id)->first();
        $this->assertNotNull($keluarga);
        $this->assertSame('6371010101000088', $keluarga->no_kk);
    }

    public function test_cascading_reset_anak_saat_induk_berubah(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('superadmin', 'superadmin', null);
        $this->actingAs($admin);

        Livewire::test(CreateKeluarga::class)
            ->fillForm([
                'provinsi_id' => $w['provinsi']->id,
                'kabkota_id' => $w['kabupaten']->id,
                'kecamatan_id' => $w['kecamatan']->id,
                'kelurahan_id' => $w['desa']->id,
                'rw_id' => $w['rw']->id,
                'id_wilayah' => $w['rt1']->id,
            ])
            ->assertFormSet([
                'provinsi_id' => $w['provinsi']->id,
                'kabkota_id' => $w['kabupaten']->id,
                'rw_id' => $w['rw']->id,
                'id_wilayah' => $w['rt1']->id,
            ])
            // Ubah provinsi -> semua anak harus direset
            ->fillForm(['provinsi_id' => null])
            ->assertFormSet([
                'kabkota_id' => null,
                'kecamatan_id' => null,
                'kelurahan_id' => null,
                'rw_id' => null,
                'id_wilayah' => null,
            ]);
    }
}
