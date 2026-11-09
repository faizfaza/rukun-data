<?php

namespace App\Filament\Resources\KeluargaResource\Pages;

use App\Filament\Resources\KeluargaResource;
use App\Filament\Schemas\Profil;
use App\Models\Anggota;
use App\Models\Keluarga;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                ])->columns(3),

            Section::make('Anggota')
                ->description('Untuk menghapus satu anggota, buka Ubah lalu gunakan ikon tempat sampah pada kartu anggota, kemudian Simpan keluarga.')
                ->schema([
                    RepeatableEntry::make('anggota')
                        ->label('')
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
                ]),

            Section::make('Ekonomi')->schema([
                RepeatableEntry::make('ekonomi')
                    ->label('')
                    ->schema([
                        TextEntry::make('tahun_data')->label('Tahun'),
                        TextEntry::make('kategori_ekonomi')->label('Kategori'),
                    ])
                    ->columns(2),
            ])->collapsible(),

            Section::make('Aset & Fasilitas (Opsional)')->schema([
                RepeatableEntry::make('aset')
                    ->label('')
                    ->schema([
                        TextEntry::make('jenis_aset')->label('Jenis Aset / Fasilitas'),
                        TextEntry::make('keterangan')->label('Keterangan')->placeholder('—'),
                    ])
                    ->columns(2),
            ])->collapsible(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
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
                ->disabled(fn (): bool => $this->record->isLocked() && ! (bool) auth()->user()?->isSuperadmin()),
        ];
    }
}
