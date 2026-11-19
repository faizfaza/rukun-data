<?php

namespace App\Filament\Resources\KeluargaResource\Pages;

use App\Filament\Pages\KonfirmasiPengajuan;
use App\Filament\Resources\KeluargaResource;
use App\Filament\Schemas\Profil;
use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\ValidasiPerubahan;
use App\Models\Wilayah;
use App\Observers\AuditObserver;
use App\Support\AuditFormatter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

// PRD F5/§7.5: dekripsi penuh hanya di halaman detail untuk peran berhak.
class ViewKeluarga extends ViewRecord
{
    protected static string $resource = KeluargaResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Keluarga')
                // DESIGN.md §7.4: keterangan privasi pada data yang tampil penuh.
                ->description('Data pribadi, jangan dibagikan di luar keperluan pendataan.')
                ->schema([
                    // Keputusan 5/2.4: admin_rw (saat rw_lihat_penuh=false) melihat
                    // No KK tersamarkan; superadmin & admin_rt melihat penuh.
                    TextEntry::make('no_kk')
                        ->label('No KK')
                        ->icon(fn (): ?string => auth()->user()?->bolehLihatDataPenuh() ? null : 'heroicon-m-lock-closed')
                        ->formatStateUsing(fn (?string $state, Keluarga $record): ?string => auth()->user()?->bolehLihatDataPenuh()
                            ? $state
                            : $record->no_kk_masked),
                    TextEntry::make('wilayah.nama')
                        ->label('RT & Wilayah')
                        ->formatStateUsing(fn (?string $state, Keluarga $record): string => $record->wilayah
                            ? $record->wilayah->alamatLengkap()
                            : '—'
                        ),
                    TextEntry::make('is_locked')
                        ->label('Status Data')
                        ->badge()
                        ->color(fn (Keluarga $record): string => $record->isLocked() ? 'danger' : 'success')
                        ->formatStateUsing(fn (Keluarga $record): string => $record->isLocked() ? 'Terkunci (Hanya Superadmin)' : 'Terbuka'),
                    TextEntry::make('alamat')->label('Alamat Domisili')->columnSpanFull(),
                    TextEntry::make('alamat_ktp')
                        ->label('Alamat KTP')
                        ->formatStateUsing(fn (?string $state, Keluarga $record): string => $record->alamat_ktp ?: ($record->alamat ?: '—'))
                        ->columnSpanFull(),
                    TextEntry::make('kode_pos')->label('Kode pos'),
                ])->columns(3)->columnSpanFull(),

            // Ekonomi & aset pendek: berdampingan tepat di bawah Data Keluarga,
            // supaya tidak ada ruang kosong di samping daftar anggota yang panjang.
            Grid::make(2)->schema([
                Section::make('Ekonomi')->schema([
                    RepeatableEntry::make('ekonomi')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('tahun_data')->label('Tahun'),
                            TextEntry::make('kategori_ekonomi')->label('Kategori'),
                        ])
                        ->columns(2),
                ])->collapsible(),

                Section::make('Aset & Fasilitas (Opsional)')->schema([
                    RepeatableEntry::make('aset')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('jenis_aset')->label('Jenis Aset / Fasilitas'),
                            TextEntry::make('keterangan')->label('Keterangan')->placeholder('—'),
                        ])
                        ->columns(2),
                ])->collapsible(),
            ])->columnSpanFull(),

            Section::make('Anggota')
                ->description('Untuk menghapus satu anggota, buka Ubah lalu gunakan ikon tempat sampah pada kartu anggota, kemudian Simpan keluarga.')
                ->schema([
                    RepeatableEntry::make('anggota')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('nik')
                                ->label('NIK')
                                ->icon(fn (): ?string => auth()->user()?->bolehLihatDataPenuh() ? null : 'heroicon-m-lock-closed')
                                ->formatStateUsing(fn (?string $state, Anggota $record): ?string => auth()->user()?->bolehLihatDataPenuh()
                                    ? $state
                                    : $record->nik_masked),
                            TextEntry::make('nama_lengkap')->label('Nama'),
                            TextEntry::make('status_kependudukan')
                                ->label('Status')
                                ->badge()
                                ->color(fn (?string $state): string => match ($state) {
                                    'pindah' => 'warning',
                                    'meninggal' => 'danger',
                                    default => 'success',
                                })
                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                    'pindah' => 'Pindah',
                                    'meninggal' => 'Meninggal',
                                    default => 'Menetap (Aktif)',
                                }),
                            TextEntry::make('hubungan.nama')->label('Hubungan'),
                            TextEntry::make('jenis_kelamin')->label('L/P'),
                            TextEntry::make('tanggal_lahir')->label('Lahir')->date('d M Y'),
                            TextEntry::make('pendidikan.nama')->label('Pendidikan'),
                            TextEntry::make('pekerjaan.nama')->label('Pekerjaan'),
                            ...Profil::detail(),
                        ])
                        ->columns(4),
                ])->columnSpanFull(),

            Section::make('Arsip Anggota Keluarga (Pernah Dihapus / Keluar dari KK)')
                ->description('Anggota yang pernah dihapus dari KK ini (misal pecah KK, cerai, atau pindah KK). Bisa dipulihkan lewat tombol Pulihkan Anggota Terhapus di kanan atas.')
                ->schema([
                    RepeatableEntry::make('anggotaTrashed')
                        ->hiddenLabel()
                        ->schema([
                            TextEntry::make('nik')
                                ->label('NIK')
                                ->icon(fn (): ?string => auth()->user()?->bolehLihatDataPenuh() ? null : 'heroicon-m-lock-closed')
                                ->formatStateUsing(fn (?string $state, Anggota $record): ?string => auth()->user()?->bolehLihatDataPenuh()
                                    ? $state
                                    : $record->nik_masked),
                            TextEntry::make('nama_lengkap')->label('Nama'),
                            TextEntry::make('hubungan.nama')->label('Hubungan Terakhir'),
                            TextEntry::make('status_kawin')
                                ->label('Status Kawin')
                                ->formatStateUsing(fn (?string $state): string => match ($state) {
                                    'belum_kawin' => 'Belum kawin',
                                    'kawin' => 'Kawin',
                                    'cerai_hidup' => 'Cerai hidup',
                                    'cerai_mati' => 'Cerai mati',
                                    default => $state ?? '—',
                                }),
                            TextEntry::make('deleted_at')
                                ->label('Diarsipkan Pada')
                                ->badge()
                                ->color('warning')
                                ->dateTime('d M Y H:i'),
                        ])
                        ->columns(5),
                ])
                ->collapsible()
                ->columnSpanFull()
                ->visible(fn (Keluarga $record): bool => $record->anggota()->onlyTrashed()->exists()),

            Section::make('Riwayat & Log Aktivitas Keluarga')
                ->description('Catatan riwayat perubahan data keluarga, status kependudukan, penambahan, dan penghapusan anggota.')
                ->icon('heroicon-o-clipboard-document-list')
                ->schema([
                    ViewEntry::make('riwayat_aktivitas')
                        ->hiddenLabel()
                        ->view('filament.infolists.keluarga-activity-log'),
                ])
                ->collapsible()
                ->columnSpanFull(),
        ])
            ->columns(1);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ajukan_pindah')
                ->label('Ajukan Pindah / Mutasi')
                ->icon('heroicon-o-truck')
                ->color('warning')
                ->visible(fn (): bool => ! $this->record->isLocked() || (bool) auth()->user()?->isSuperadmin() || (bool) auth()->user()?->isAdminDesa())
                ->modalHeading('Pengajuan Pindah / Mutasi Warga Berjenjang')
                ->modalDescription('Pengajuan dari Admin RT akan dikirim ke Admin RW lalu ke Admin Desa / Kepala Desa untuk disahkan.')
                ->modalSubmitActionLabel('Kirim Pengajuan Pindah')
                ->form([
                    Select::make('jenis_mutasi')
                        ->label('Cakupan Kepindahan')
                        ->options([
                            'keluarga' => 'Pindah Satu Keluarga (Seluruh Anggota KK)',
                            'anggota' => 'Pindah Anggota Keluarga Tertentu',
                        ])
                        ->default('keluarga')
                        ->required()
                        ->live(),

                    Select::make('id_anggota')
                        ->label('Pilih Anggota yang Pindah')
                        ->options(fn (): array => $this->record->anggota()
                            ->get()
                            ->mapWithKeys(fn (Anggota $a): array => [$a->id => "{$a->nama_lengkap} ({$a->nik_masked})"])
                            ->all()
                        )
                        ->visible(fn (Get $get): bool => $get('jenis_mutasi') === 'anggota')
                        ->required(fn (Get $get): bool => $get('jenis_mutasi') === 'anggota'),

                    Select::make('tipe_tujuan')
                        ->label('Tujuan Kepindahan')
                        ->options([
                            'keluar_desa' => 'Pindah Keluar Desa / Kelurahan',
                            'dalam_desa' => 'Pindah Antar RT / RW di Dalam Desa',
                        ])
                        ->default('keluar_desa')
                        ->required()
                        ->live(),

                    Select::make('id_wilayah_tujuan')
                        ->label('Pilih RT Tujuan di Desa Ini')
                        ->options(function (): array {
                            $desa = $this->record->wilayah?->desaInduk();
                            $query = Wilayah::operasional()->where('level', 'rt');
                            if ($desa) {
                                $query->where('path', 'like', $desa->path.'%');
                            }

                            return $query->orderBy('path')
                                ->get()
                                ->mapWithKeys(fn (Wilayah $w): array => [$w->id => $w->alamatLengkap()])
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->visible(fn (Get $get): bool => $get('tipe_tujuan') === 'dalam_desa')
                        ->required(fn (Get $get): bool => $get('tipe_tujuan') === 'dalam_desa'),

                    Textarea::make('alamat_tujuan')
                        ->label('Alamat Tujuan Baru')
                        ->placeholder('Contoh: Jl. Melati No. 12, RT 03 / RW 02')
                        ->rows(2)
                        ->required(),

                    Textarea::make('catatan_pengaju')
                        ->label('Keterangan / Alasan Pindah')
                        ->placeholder('Contoh: Pindah mengikuti tempat tugas baru sesuai surat pengantar RT.')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $user = auth()->user();
                    abort_unless($user !== null, 403);

                    $rtAsal = $this->record->wilayah;
                    $rwAsal = $rtAsal?->rwInduk();
                    $desaAsal = $rtAsal?->desaInduk() ?? $user->desaWilayah();
                    $rtTujuanId = ($data['tipe_tujuan'] ?? '') === 'dalam_desa' && ! empty($data['id_wilayah_tujuan'])
                        ? (int) $data['id_wilayah_tujuan']
                        : null;
                    $rtTujuan = $rtTujuanId ? Wilayah::find($rtTujuanId) : null;

                    $statusAwal = match (true) {
                        $user->isAdminRt() => ValidasiPerubahan::STATUS_MENUNGGU_RW,
                        $user->isAdminRw() => ValidasiPerubahan::STATUS_MENUNGGU_DESA,
                        default => ValidasiPerubahan::STATUS_DISETUJUI,
                    };

                    $langsungDisahkan = $statusAwal === ValidasiPerubahan::STATUS_DISETUJUI;
                    $tiketBaru = null;

                    if (($data['jenis_mutasi'] ?? 'keluarga') === 'anggota' && ! empty($data['id_anggota'])) {
                        $targetAnggota = Anggota::withoutGlobalScopes()->find((int) $data['id_anggota']);
                        abort_unless($targetAnggota !== null, 404);

                        $sebelumSnap = AuditFormatter::snapshotModel($targetAnggota);
                        $rawBefore = $targetAnggota->getRawOriginal();
                        unset($rawBefore['id'], $rawBefore['created_at'], $rawBefore['updated_at']);

                        if ($langsungDisahkan) {
                            Model::withoutEvents(function () use ($targetAnggota): void {
                                $targetAnggota->update(['status_kependudukan' => 'pindah']);
                            });
                            $sesudahSnap = AuditFormatter::snapshotModel($targetAnggota->fresh());
                        } else {
                            $sesudahSnap = array_merge($sebelumSnap, [
                                'Status Kependudukan' => 'Pindah',
                            ]);
                        }

                        $tujuanLabel = $rtTujuan ? $rtTujuan->alamatLengkap() : ($data['alamat_tujuan'] ?? 'Keluar Desa');
                        $ringkasan = "Mutasi Pindah Anggota '{$targetAnggota->nama_lengkap}' ke {$tujuanLabel} (Asal: ".($rtAsal?->alamatLengkap() ?? '—').').';

                        $tiketBaru = ValidasiPerubahan::create([
                            'id_pengguna' => $user->id,
                            'peran_pengaju' => $user->role?->value,
                            'aksi' => 'pindah',
                            'nama_tabel' => 'anggota',
                            'id_record' => $targetAnggota->id,
                            'id_keluarga' => $this->record->id,
                            'id_wilayah_rt' => $rtAsal?->id,
                            'id_wilayah_rw' => $rwAsal?->id,
                            'id_wilayah_desa' => $desaAsal?->id,
                            'id_wilayah_tujuan' => $rtTujuan?->id,
                            'status' => $statusAwal,
                            'ringkasan' => $ringkasan,
                            'catatan_pengaju' => $data['catatan_pengaju'] ?? null,
                            'id_verifikator_rw' => $langsungDisahkan ? $user->id : null,
                            'waktu_verifikasi_rw' => $langsungDisahkan ? now() : null,
                            'id_verifikator_desa' => $langsungDisahkan ? $user->id : null,
                            'waktu_verifikasi_desa' => $langsungDisahkan ? now() : null,
                            'payload' => [
                                'ringkasan' => $ringkasan,
                                'kalimat' => [$ringkasan],
                                'perubahan' => [
                                    [
                                        'label' => 'Status Kependudukan',
                                        'sebelum' => $sebelumSnap['Status Kependudukan'] ?? 'Menetap (Aktif)',
                                        'sesudah' => 'Pindah',
                                    ],
                                    [
                                        'label' => 'Alamat / Wilayah Tujuan',
                                        'sebelum' => $rtAsal?->alamatLengkap() ?? '—',
                                        'sesudah' => $tujuanLabel,
                                    ],
                                ],
                                'sebelum' => $sebelumSnap,
                                'sesudah' => $sesudahSnap,
                                'raw_before' => $rawBefore,
                                'deferred' => ! $langsungDisahkan,
                                'data_pindah' => [
                                    'jenis_mutasi' => 'anggota',
                                    'id_anggota' => $targetAnggota->id,
                                    'id_wilayah_tujuan' => $rtTujuan?->id,
                                    'alamat_tujuan' => $data['alamat_tujuan'] ?? null,
                                ],
                            ],
                        ]);
                    } else {
                        $sebelumSnap = AuditFormatter::snapshotModel($this->record);
                        $rawBefore = $this->record->getRawOriginal();
                        unset($rawBefore['id'], $rawBefore['created_at'], $rawBefore['updated_at']);

                        $tujuanLabel = $rtTujuan ? $rtTujuan->alamatLengkap() : ($data['alamat_tujuan'] ?? 'Keluar Desa');

                        if ($langsungDisahkan) {
                            Model::withoutEvents(function () use ($rtTujuan, $data): void {
                                if ($rtTujuan) {
                                    $this->record->update([
                                        'id_wilayah' => $rtTujuan->id,
                                        'alamat' => $data['alamat_tujuan'] ?: $this->record->alamat,
                                    ]);
                                } else {
                                    $this->record->anggota()->update(['status_kependudukan' => 'pindah']);
                                }
                            });
                            $sesudahSnap = AuditFormatter::snapshotModel($this->record->fresh());
                        } else {
                            $sesudahSnap = array_merge($sebelumSnap, [
                                'Wilayah RT / RW' => $tujuanLabel,
                                'Alamat Domisili' => $data['alamat_tujuan'] ?: ($sebelumSnap['Alamat Domisili'] ?? '—'),
                            ]);
                        }

                        $kepalaNama = $this->record->kepala?->nama_lengkap ?? $this->record->no_kk_masked;
                        $ringkasan = "Mutasi Pindah Keluarga 'KK {$kepalaNama}' dari ".($rtAsal?->nama ?? 'RT')." ke {$tujuanLabel}.";

                        $tiketBaru = ValidasiPerubahan::create([
                            'id_pengguna' => $user->id,
                            'peran_pengaju' => $user->role?->value,
                            'aksi' => 'pindah',
                            'nama_tabel' => 'keluarga',
                            'id_record' => $this->record->id,
                            'id_keluarga' => $this->record->id,
                            'id_wilayah_rt' => $rtAsal?->id,
                            'id_wilayah_rw' => $rwAsal?->id,
                            'id_wilayah_desa' => $desaAsal?->id,
                            'id_wilayah_tujuan' => $rtTujuan?->id,
                            'status' => $statusAwal,
                            'ringkasan' => $ringkasan,
                            'catatan_pengaju' => $data['catatan_pengaju'] ?? null,
                            'id_verifikator_rw' => $langsungDisahkan ? $user->id : null,
                            'waktu_verifikasi_rw' => $langsungDisahkan ? now() : null,
                            'id_verifikator_desa' => $langsungDisahkan ? $user->id : null,
                            'waktu_verifikasi_desa' => $langsungDisahkan ? now() : null,
                            'payload' => [
                                'ringkasan' => $ringkasan,
                                'kalimat' => [$ringkasan],
                                'perubahan' => [
                                    [
                                        'label' => 'Wilayah / Status Domisili KK',
                                        'sebelum' => $rtAsal?->alamatLengkap() ?? '—',
                                        'sesudah' => $tujuanLabel,
                                    ],
                                    [
                                        'label' => 'Alamat Domisili',
                                        'sebelum' => $sebelumSnap['Alamat Domisili'] ?? '—',
                                        'sesudah' => $data['alamat_tujuan'] ?? '—',
                                    ],
                                ],
                                'sebelum' => $sebelumSnap,
                                'sesudah' => $sesudahSnap,
                                'raw_before' => $rawBefore,
                                'deferred' => ! $langsungDisahkan,
                                'data_pindah' => [
                                    'jenis_mutasi' => 'keluarga',
                                    'id_wilayah_tujuan' => $rtTujuan?->id,
                                    'alamat_tujuan' => $data['alamat_tujuan'] ?? null,
                                ],
                            ],
                        ]);
                    }

                    Notification::make()
                        ->title('Pengajuan Pindah Terkirim')
                        ->body(match ($statusAwal) {
                            ValidasiPerubahan::STATUS_MENUNGGU_RW => 'Pengajuan mutasi telah dikirim ke Admin RW untuk diverifikasi lalu diteruskan ke Desa. Data utama belum berubah sampai disahkan oleh Kepala Desa.',
                            ValidasiPerubahan::STATUS_MENUNGGU_DESA => 'Pengajuan mutasi telah dikirim ke Admin Desa / Kepala Desa untuk pengesahan. Data utama belum berubah sampai disahkan oleh Kepala Desa.',
                            default => 'Mutasi pindah telah langsung disahkan.',
                        })
                        ->success()
                        ->send();

                    if (! $langsungDisahkan && $tiketBaru) {
                        $this->redirect(KonfirmasiPengajuan::urlForTickets([$tiketBaru->id]), navigate: true);
                    }
                }),

            Action::make('pulihkan_anggota')
                ->label('Pulihkan Anggota Terhapus')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->visible(fn (): bool => $this->record->anggota()->onlyTrashed()->exists()
                    && (! $this->record->isLocked() || (bool) auth()->user()?->isSuperadmin()))
                ->requiresConfirmation()
                ->modalHeading('Pulihkan Anggota dari Arsip')
                ->modalDescription('Seluruh anggota yang diarsipkan (terhapus) pada Kartu Keluarga ini akan dikembalikan menjadi anggota aktif di KK ini.')
                ->action(function (): void {
                    $dipulihkan = $this->record->anggota()->onlyTrashed()->restore();
                    Notification::make()
                        ->title("{$dipulihkan} anggota berhasil dipulihkan ke KK ini")
                        ->success()
                        ->send();
                }),
            Action::make('cetak')
                ->label('Cetak Kartu Keluarga')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn (): string => route('keluarga.cetak', $this->record))
                ->openUrlInNewTab(),
            Action::make('toggle_lock')
                ->label(fn (): string => $this->record->isLocked() ? 'Buka Kunci' : 'Kunci Data')
                ->icon(fn (): string => $this->record->isLocked() ? 'heroicon-o-lock-open' : 'heroicon-s-lock-closed')
                ->color(fn (): string => $this->record->isLocked() ? 'warning' : 'danger')
                ->visible(fn (): bool => (bool) auth()->user()?->isSuperadmin())
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->record->isLocked() ? 'Buka Kunci Data Keluarga' : 'Kunci Data Keluarga')
                ->modalDescription(fn (): string => $this->record->isLocked()
                    ? 'Data keluarga ini akan dibuka kuncinya agar admin RT/RW dapat mengedit kembali.'
                    : 'Data keluarga ini akan dikunci. Hanya Superadmin yang dapat mengubah atau menghapus data ini.'
                )
                ->action(function (): void {
                    $this->record->update(['is_locked' => ! $this->record->is_locked]);
                    Notification::make()
                        ->title($this->record->is_locked ? 'Data keluarga berhasil dikunci' : 'Kunci data keluarga berhasil dibuka')
                        ->success()
                        ->send();
                }),
            EditAction::make()
                ->disabled(fn (): bool => $this->record->isLocked() && ! (bool) auth()->user()?->isSuperadmin()),
            DeleteAction::make()
                ->disabled(fn (): bool => $this->record->isLocked() && ! (bool) auth()->user()?->isSuperadmin())
                ->successRedirectUrl(function (): ?string {
                    $ticketIds = AuditObserver::popLastCreatedTicketIds();

                    return $ticketIds !== [] ? KonfirmasiPengajuan::urlForTickets($ticketIds) : null;
                }),
        ];
    }
}
