<?php

namespace App\Filament\Resources\AnggotaResource\Pages;

use App\Filament\Resources\AnggotaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

// PRD F5: profil individu lengkap beserta keluarganya.
class ViewAnggota extends ViewRecord
{
    protected static string $resource = AnggotaResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil Individu')
                // DESIGN.md §7.4: keterangan privasi pada data yang tampil penuh.
                ->description('Data pribadi, jangan dibagikan di luar keperluan pendataan.')
                ->schema([
                TextEntry::make('nik')->label('NIK'),
                TextEntry::make('nama_lengkap')->label('Nama lengkap'),
                TextEntry::make('tempat_lahir')->label('Tempat lahir'),
                TextEntry::make('tanggal_lahir')->label('Tanggal lahir')->date('d M Y'),
                TextEntry::make('jenis_kelamin')->label('Jenis kelamin'),
                TextEntry::make('status_kawin')->label('Status kawin'),
                TextEntry::make('agama.nama')->label('Agama'),
                TextEntry::make('pendidikan.nama')->label('Pendidikan'),
                TextEntry::make('pekerjaan.nama')->label('Pekerjaan'),
                TextEntry::make('hubungan.nama')->label('Hubungan dalam keluarga'),
                TextEntry::make('nama_ayah')->label('Nama ayah'),
                TextEntry::make('nama_ibu')->label('Nama ibu'),
                TextEntry::make('penghasilan_bulanan')->label('Penghasilan/bulan (Rp)'),
            ])->columns(3),

            Section::make('Keluarga')->schema([
                TextEntry::make('keluarga.no_kk')->label('No KK'),
                TextEntry::make('keluarga.wilayah.nama')->label('RT'),
                TextEntry::make('keluarga.alamat')->label('Alamat')->columnSpanFull(),
                TextEntry::make('keluarga.kode_pos')->label('Kode pos'),
            ])->columns(3),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make(), DeleteAction::make()];
    }
}
