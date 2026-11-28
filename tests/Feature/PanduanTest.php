<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Filament\Pages\Panduan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanduanTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_akun_baru_wajib_menuntaskan_panduan_dan_bisa_mengulang(): void
    {
        $wilayah = $this->seedDasar();
        $user = $this->makeUser('baru', 'admin_rt', $wilayah['rt1']->id, panduanSelesai: false);
        $lain = $this->makeUser('lain', 'admin_rt', $wilayah['rt1']->id, panduanSelesai: false);

        $this->get('/admin/panduan')->assertRedirect('/admin/login');
        $this->actingAs($user);
        $this->get('/admin/keluarga/create')->assertRedirect('/admin/panduan');
        $this->get('/admin/panduan')->assertOk()
            ->assertSee('Langkah 1 dari 3')
            ->assertDontSee('Keluar dari panduan');

        Livewire::test(Panduan::class)
            ->call('kembali')->assertSet('langkah', 0)
            ->call('lanjut')->assertSee('Isi contoh No KK')
            ->call('kembali')->assertSet('langkah', 0)
            ->call('lanjut')->call('lanjut')->assertSee('Coba simpan keluarga');

        $this->assertNull($user->fresh()->panduan_selesai_at);
        $this->post('/admin/logout')->assertRedirect();
        $this->assertGuest();

        $this->actingAs($user);
        $this->get('/admin')->assertRedirect('/admin/panduan');
        $this->get('/admin/panduan')->assertSee('Langkah 1 dari 3');

        $tur = Livewire::test(Panduan::class)->call('lanjut')->call('lanjut');
        $tur->call('lanjut')->assertRedirect('/admin');
        $this->assertNotNull($selesai = $user->refresh()->panduan_selesai_at);
        $this->assertNull($lain->fresh()->panduan_selesai_at);
        $this->assertDatabaseCount('keluarga', 0);
        $this->assertDatabaseCount('anggota', 0);

        $this->get('/admin')->assertOk()->assertSee('/admin/panduan');
        $this->get('/admin/panduan')->assertOk()->assertSee('Keluar dari panduan');
        $this->travel(1)->hour();
        Livewire::test(Panduan::class)->call('lanjut')->call('lanjut')->call('lanjut')->assertRedirect('/admin');
        $this->assertEquals($selesai, $user->fresh()->panduan_selesai_at);
    }

    #[DataProvider('peran')]
    public function test_isi_panduan_mengikuti_peran(Role $role, ?string $wilayahKey, string $judul, int $jumlahLangkah): void
    {
        $wilayah = $this->seedDasar();
        $user = $this->makeUser('pengguna', $role->value, $wilayahKey ? $wilayah[$wilayahKey]->id : null);
        $this->actingAs($user);

        $tur = Livewire::test(Panduan::class)
            ->assertSee($judul)
            ->assertSee("Langkah 1 dari {$jumlahLangkah}")
            ->call('lanjut')->call('lanjut');

        if ($role === Role::AdminRt) {
            $tur->assertSee('Selesai');

            return;
        }

        $tur->call('lanjut')->assertSee('Kelola wilayah');
        if ($role === Role::AdminRw) {
            $tur->assertSee('Selesai')->assertDontSee('Buat akun dari Kelola Admin');

            return;
        }

        $tur->call('lanjut')->assertSee('Buat akun dari Kelola Admin')
            ->assertSee($role === Role::Admin ? 'Peran yang tersedia: Admin RW, Admin RT.' : 'Peran yang tersedia: Superadmin, Admin, Admin RW, Admin RT.');
    }

    public static function peran(): array
    {
        return [
            [Role::AdminRt, 'rt1', 'Buka RT penugasan', 3],
            [Role::AdminRw, 'rw', 'Pilih RT di bawah RW Anda', 4],
            [Role::Admin, null, 'Temukan RT yang dituju', 5],
            [Role::Superadmin, null, 'Temukan RT yang dituju', 5],
        ];
    }
}
