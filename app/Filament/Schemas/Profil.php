<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

final class Profil
{
    public const FIELDS = [
        'status_sekolah' => 'Status sekolah', 'nama_sekolah' => 'Nama sekolah / perguruan tinggi',
        'jenjang_berjalan' => 'Jenjang yang sedang ditempuh', 'kelas_program' => 'Kelas / program studi',
        'lokasi_sekolah' => 'Lokasi institusi', 'tanggal_perkawinan' => 'Tanggal perkawinan',
        'tempat_perkawinan' => 'Tempat perkawinan', 'pencatatan_perkawinan' => 'Pencatatan perkawinan',
        'nomor_perkawinan' => 'Nomor akta / buku nikah', 'tanggal_perceraian' => 'Tanggal perceraian',
        'nomor_perceraian' => 'Nomor akta perceraian', 'kewarganegaraan' => 'Kewarganegaraan',
        'nomor_akta_lahir' => 'Nomor akta kelahiran',
    ];

    public static function anggota(): array
    {
        $sections = [];
        foreach (['Pendidikan berjalan' => ['status_sekolah', 'nama_sekolah', 'jenjang_berjalan', 'kelas_program', 'lokasi_sekolah'],
            'Pencatatan perkawinan' => ['tanggal_perkawinan', 'tempat_perkawinan', 'pencatatan_perkawinan', 'nomor_perkawinan', 'tanggal_perceraian', 'nomor_perceraian'],
            'Administrasi kependudukan' => ['kewarganegaraan', 'nomor_akta_lahir']] as $title => $keys) {
            $fields = [];
            foreach ($keys as $key) {
                $field = str_starts_with($key, 'tanggal_') ? DatePicker::make('profil.'.$key)->maxDate(now()) : TextInput::make('profil.'.$key)->maxLength(200);
                if ($key === 'status_sekolah') {
                    $field = Select::make('profil.'.$key)->options(['belum_sekolah' => 'Belum sekolah', 'bersekolah' => 'Sedang bersekolah', 'tidak_bersekolah' => 'Tidak sedang bersekolah']);
                }
                if ($key === 'pencatatan_perkawinan') {
                    $field = Select::make('profil.'.$key)->options(['tercatat' => 'Tercatat', 'belum_tercatat' => 'Belum tercatat', 'tidak_diketahui' => 'Tidak diketahui']);
                }
                if (str_contains($key, 'perceraian')) {
                    $field->visible(fn (Get $get) => $get('status_kawin') === 'cerai_hidup');
                }
                $fields[] = $field->label(self::FIELDS[$key])->nullable();
            }
            $sections[] = Section::make($title)->schema($fields)->columns(2)->collapsible()->columnSpanFull();
        }

        return $sections;
    }

    public static function detail(): array
    {
        return [Section::make('Profil tambahan')->schema(array_map(fn ($key) => TextEntry::make('profil.'.$key)->label(self::FIELDS[$key])->placeholder('—'), array_keys(self::FIELDS)))->columns(3)->collapsible()];
    }

    public static function admin(): Section
    {
        return Section::make('Identitas Admin')->description('Opsional. Isi informasi yang diketahui. Data disimpan terenkripsi.')->schema([
            TextInput::make('identitas.nama_lengkap')->label('Nama lengkap identitas')->maxLength(100),
            TextInput::make('identitas.nik')->label('NIK')->nullable()->rule('regex:/^[0-9]{16}$/'),
            TextInput::make('identitas.kontak')->label('Nomor kontak')->tel()->maxLength(30),
            Textarea::make('identitas.alamat')->label('Alamat')->maxLength(500),
            TextInput::make('identitas.tempat_lahir')->label('Tempat lahir')->maxLength(100),
            DatePicker::make('identitas.tanggal_lahir')->label('Tanggal lahir')->maxDate(now()),
            Select::make('identitas.jenis_kelamin')->label('Jenis kelamin')->options(['L' => 'Laki-laki', 'P' => 'Perempuan']),
            TextInput::make('identitas.jabatan')->label('Jabatan / keterangan penugasan')->maxLength(200),
        ])->columns(2)->collapsible()->columnSpanFull();
    }
}
