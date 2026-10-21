<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// R-35: setiap halaman panel harus termuat tanpa error untuk peran berhak,
// dan tamu diarahkan ke login.
class PanelSmokeTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_semua_halaman_utama_termuat(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);
        $keluarga = $this->makeKeluarga($w['rt1']->id, '1111111111111111', [
            ['nik' => '1111111111111111', 'hubungan' => 1],
        ], $admin->id);
        $anggotaId = $keluarga->anggota()->first()->id;

        $this->actingAs($admin);

        foreach ([
            '/admin',
            '/admin/jelajah',
            '/admin/keluarga',
            '/admin/keluarga/create',
            "/admin/keluarga/{$keluarga->id}",
            "/admin/keluarga/{$keluarga->id}/edit",
            "/admin/anggota/{$anggotaId}",
            "/admin/anggota/{$anggotaId}/edit",
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    // Fase 3.6: daftar datar anggota tidak boleh ada lagi.
    public function test_daftar_datar_anggota_tidak_ada(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);
        $this->actingAs($admin);

        $this->get('/admin/anggota')->assertNotFound();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/keluarga')->assertRedirect('/admin/login');
        $this->get('/admin/jelajah')->assertRedirect('/admin/login');
    }
}
