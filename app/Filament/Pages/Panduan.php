<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class Panduan extends Page
{
    protected static ?string $title = 'Panduan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.panduan';

    #[Locked]
    public int $langkah = 0;

    public function lanjut(): void
    {
        $user = $this->pengguna();

        if ($this->langkah < count($this->materi($user->role)) - 1) {
            $this->langkah++;

            return;
        }

        User::query()->whereKey($user->getKey())->whereNull('panduan_selesai_at')
            ->update(['panduan_selesai_at' => now()]);

        $this->redirect(Dashboard::getUrl());
    }

    public function kembali(): void
    {
        $this->langkah = max(0, $this->langkah - 1);
    }

    protected function getViewData(): array
    {
        $user = $this->pengguna();
        $materi = $this->materi($user->role);

        return [
            'role' => $user->role->label(),
            'sudahSelesai' => $user->panduan_selesai_at !== null,
            'jumlahLangkah' => count($materi),
            'judul' => $materi[$this->langkah][0],
            'isi' => $materi[$this->langkah][1],
            'sorot' => $materi[$this->langkah][2],
        ];
    }

    private function pengguna(): User
    {
        $user = Filament::auth()->user();
        abort_unless($user instanceof User && $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()), 403);

        return $user;
    }

    private function materi(Role $role): array
    {
        $wilayah = match ($role) {
            Role::AdminRt => ['Buka RT penugasan', 'Buka Jelajah Wilayah. Daftar keluarga langsung tampil untuk RT penugasan Anda.', 'jelajah'],
            Role::AdminRw => ['Pilih RT di bawah RW Anda', 'Buka Jelajah Wilayah dan pilih RT. Daftar keluarga tampil setelah RT dipilih.', 'jelajah'],
            Role::AdminDesa => ['Pantau RW & RT di Desa Anda', 'Buka Jelajah Wilayah untuk memilih RW dan RT di desa Anda, serta sahkan pengajuan pada menu Validasi & Persetujuan.', 'jelajah'],
            Role::Admin, Role::Superadmin => ['Temukan RT yang dituju', 'Buka Jelajah Wilayah. Pilih provinsi dan lanjutkan hingga RT. Daftar keluarga tampil setelah RT dipilih.', 'jelajah'],
        };

        $langkah = [
            $wilayah,
            ['Cari keluarga', 'Di Jelajah Wilayah, pilih Cari No KK / NIK dan masukkan nomor lengkap 16 digit untuk membuka detail keluarga.', 'jelajah'],
            ['Coba tambah keluarga', 'Pilih Tambah keluarga. Isi data KK dan anggota, lalu tetapkan satu anggota sebagai kepala keluarga sebelum menyimpan.', 'jelajah'],
        ];

        if ($role !== Role::AdminRt) {
            $langkah[] = ['Kelola wilayah', $role === Role::AdminRw
                ? 'Buka menu Wilayah untuk menambah atau mengubah RT di bawah RW penugasan Anda.'
                : 'Buka menu Wilayah untuk menelusuri hierarki dan mengelola wilayah.', 'wilayah'];
        }

        if ($role->bolehMembuat() !== []) {
            $peran = implode(', ', array_map(fn (Role $item): string => $item->label(), $role->bolehMembuat()));
            $langkah[] = ['Buat akun dari Kelola Admin', "Buka Kelola Admin dan pilih peran akun yang akan dibuat. Peran yang tersedia: {$peran}. Untuk petugas, tentukan RW atau RT penugasannya.", 'pengguna'];
        }

        return $langkah;
    }
}
