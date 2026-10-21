<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

// PRD F1/§14.1: login memakai username; kredensial salah ditolak.
class UsernameLoginTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_halaman_login_memakai_username(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Username', false);
    }

    public function test_login_username_berhasil(): void
    {
        $w = $this->seedDasar();
        $this->makeUser('petugas-rt1', 'admin_rt', $w['rt1']->id);

        Livewire::test(Login::class)
            ->fillForm(['username' => 'petugas-rt1', 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticated();
    }

    public function test_login_gagal_ditolak(): void
    {
        $w = $this->seedDasar();
        $this->makeUser('petugas-rt1', 'admin_rt', $w['rt1']->id);

        Livewire::test(Login::class)
            ->fillForm(['username' => 'petugas-rt1', 'password' => 'salah'])
            ->call('authenticate')
            ->assertHasErrors();

        $this->assertGuest();
    }
}
