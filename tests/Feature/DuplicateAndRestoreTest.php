<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\RefHubungan;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// PRD §14.2/§14.5/§14.7: duplikat ditolak UNIQUE hash, input ulang data
// terhapus menemukan baris lama (pemulihan, bukan baris baru), dan simpan
// keluarga+anggota bersifat satu transaksi.
class DuplicateAndRestoreTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_no_kk_ganda_ditolak(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $admin->id);

        $this->expectException(QueryException::class);

        $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '3333333333333333', 'hubungan' => 1],
        ], $admin->id);
    }

    public function test_input_ulang_data_terhapus_menemukan_baris_lama(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);

        $lama = $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $admin->id);
        $lama->delete();

        $this->assertSame(0, Keluarga::count());

        $temu = Keluarga::findByNoKk('1111111111111111');

        $this->assertNotNull($temu);
        $this->assertTrue($temu->trashed());
        $this->assertSame($lama->id, $temu->id);

        $temu->restore();

        $this->assertSame(1, Keluarga::count());
    }

    public function test_simpan_gagal_rollback_seluruhnya(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'admin', null);

        try {
            DB::transaction(function () use ($w, $admin) {
                $keluarga = Keluarga::create([
                    'id_wilayah' => $w['rt1']->id,
                    'no_kk' => '1111111111111111',
                    'alamat' => 'Jl. Uji',
                    'kode_pos' => '70000',
                    'created_by' => $admin->id,
                ]);

                Anggota::create([
                    'id_keluarga' => $keluarga->id,
                    'nik' => '1111111111111111',
                    'nama_lengkap' => 'Gagal',
                    'tempat_lahir' => 'Uji',
                    'tanggal_lahir' => '1990-01-01',
                    'jenis_kelamin' => 'L',
                    'status_kawin' => 'kawin',
                    'id_agama' => 1,
                    'id_pendidikan' => 1,
                    'id_pekerjaan' => 1,
                    'id_hubungan' => RefHubungan::KEPALA,
                    'created_by' => $admin->id,
                ]);

                throw new \RuntimeException('simulasi gagal di tengah jalan');
            });
        } catch (\RuntimeException) {
            // diabaikan, yang diuji adalah rollback-nya
        }

        $this->assertSame(0, Keluarga::withoutGlobalScopes()->count());
        $this->assertSame(0, Anggota::withoutGlobalScopes()->count());
    }
}
