<?php

namespace App\Filament\Resources\KeluargaResource\Pages;

use App\Filament\Resources\KeluargaResource;
use App\Filament\Schemas\Profil;
use App\Models\Anggota;
use App\Models\Keluarga;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
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
                    TextEntry::make('alamat')->label('Alamat')->columnSpanFull(),
                    TextEntry::make('kode_pos')->label('Kode pos'),
                ])->columns(3),

            Section::make('Anggota')->schema([
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
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}
