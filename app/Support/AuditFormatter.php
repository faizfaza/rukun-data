<?php

namespace App\Support;

use App\Enums\Role;
use App\Filament\Pages\JelajahWilayah;
use App\Filament\Resources\AnggotaResource;
use App\Filament\Resources\KeluargaResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WilayahResource;
use App\Models\Anggota;
use App\Models\AsetKeluarga;
use App\Models\AuditLog;
use App\Models\EkonomiKeluarga;
use App\Models\Keluarga;
use App\Models\RefAgama;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\User;
use App\Models\Wilayah;
use App\Filament\Schemas\Profil;
use Illuminate\Database\Eloquent\Model;

class AuditFormatter
{
    /**
     * Menyusun snapshot bersih yang mudah dibaca manusia dari sebuah model.
     *
     * @return array<string, string>
     */
    public static function snapshotModel(Model $model): array
    {
        if ($model instanceof Keluarga) {
            $wilayah = Wilayah::withoutGlobalScopes()->find($model->id_wilayah);
            $kepala = Anggota::withoutGlobalScopes()
                ->withTrashed()
                ->where('id_keluarga', $model->id)
                ->where('id_hubungan', RefHubungan::KEPALA)
                ->first();

            return [
                'No. KK (Tersamar)' => $model->no_kk_masked ?? '—',
                'Kepala Keluarga' => $kepala?->nama_lengkap ?? '—',
                'Wilayah RT / RW' => $wilayah ? $wilayah->alamatLengkap() : '—',
                'Alamat Domisili' => $model->alamat ?: '—',
                'Alamat KTP' => $model->alamat_ktp ?: ($model->alamat ?: '—'),
                'Kode Pos' => $model->kode_pos ?: '—',
                'Status Kunci Data' => $model->isLocked() ? 'Terkunci' : 'Terbuka',
                'Status Arsip KK' => $model->trashed() ? 'Diarsipkan (Terhapus)' : 'Aktif',
            ];
        }

        if ($model instanceof Anggota) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($model->id_keluarga);
            $wilayah = $keluarga ? Wilayah::withoutGlobalScopes()->find($keluarga->id_wilayah) : null;
            $kepala = $keluarga
                ? Anggota::withoutGlobalScopes()->withTrashed()->where('id_keluarga', $keluarga->id)->where('id_hubungan', RefHubungan::KEPALA)->first()
                : null;

            $snap = [
                'Nama Lengkap' => $model->nama_lengkap ?: '—',
                'NIK (Tersamar)' => $model->nik_masked ?: '—',
                'Hubungan Keluarga' => RefHubungan::find($model->id_hubungan)?->nama ?? '—',
                'Status Kependudukan' => self::labelStatusKependudukan($model->status_kependudukan),
                'Status Perkawinan' => self::labelStatusKawin($model->status_kawin),
                'Jenis Kelamin' => $model->jenis_kelamin === 'P' ? 'Perempuan' : ($model->jenis_kelamin === 'L' ? 'Laki-laki' : '—'),
                'Tempat, Tanggal Lahir' => trim(($model->tempat_lahir ?: '—').', '.($model->tanggal_lahir ? $model->tanggal_lahir->format('d M Y') : '—')),
                'Agama' => RefAgama::find($model->id_agama)?->nama ?? '—',
                'Pendidikan' => RefPendidikan::find($model->id_pendidikan)?->nama ?? '—',
                'Pekerjaan' => RefPekerjaan::find($model->id_pekerjaan)?->nama ?? '—',
                'Nama Ayah' => $model->nama_ayah ?: '—',
                'Nama Ibu' => $model->nama_ibu ?: '—',
                'Penghasilan / Bulan' => $model->penghasilan_bulanan !== null && $model->penghasilan_bulanan !== ''
                    ? 'Rp '.number_format((float) $model->penghasilan_bulanan, 0, ',', '.')
                    : '—',
                'Kartu Keluarga (Kepala)' => $kepala?->nama_lengkap ?? ($keluarga?->no_kk_masked ?? '—'),
                'Wilayah RT / RW' => $wilayah ? $wilayah->alamatLengkap() : '—',
                'Status di KK' => $model->trashed() ? 'Diarsipkan (Keluar / Dihapus dari KK)' : 'Aktif di KK',
            ];

            $profil = is_array($model->profil) ? $model->profil : [];
            foreach (Profil::FIELDS as $k => $label) {
                if (! empty($profil[$k])) {
                    $snap[$label] = (string) $profil[$k];
                }
            }

            return $snap;
        }

        if ($model instanceof AsetKeluarga) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($model->id_keluarga);
            $wilayah = $keluarga ? Wilayah::withoutGlobalScopes()->find($keluarga->id_wilayah) : null;
            $kepala = $keluarga
                ? Anggota::withoutGlobalScopes()->withTrashed()->where('id_keluarga', $keluarga->id)->where('id_hubungan', RefHubungan::KEPALA)->first()
                : null;

            return [
                'Jenis Aset / Fasilitas' => $model->jenis_aset ?: '—',
                'Keterangan Aset' => $model->keterangan ?: '—',
                'Milik Keluarga (Kepala KK)' => $kepala?->nama_lengkap ?? ($keluarga?->no_kk_masked ?? '—'),
                'Wilayah RT / RW' => $wilayah ? $wilayah->alamatLengkap() : '—',
            ];
        }

        if ($model instanceof EkonomiKeluarga) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($model->id_keluarga);
            $wilayah = $keluarga ? Wilayah::withoutGlobalScopes()->find($keluarga->id_wilayah) : null;
            $kepala = $keluarga
                ? Anggota::withoutGlobalScopes()->withTrashed()->where('id_keluarga', $keluarga->id)->where('id_hubungan', RefHubungan::KEPALA)->first()
                : null;

            return [
                'Kategori Ekonomi' => $model->kategori_ekonomi ?: '—',
                'Tahun Data' => (string) ($model->tahun_data ?: '—'),
                'Milik Keluarga (Kepala KK)' => $kepala?->nama_lengkap ?? ($keluarga?->no_kk_masked ?? '—'),
                'Wilayah RT / RW' => $wilayah ? $wilayah->alamatLengkap() : '—',
            ];
        }

        if ($model instanceof Wilayah) {
            return [
                'Nama Wilayah' => $model->nama ?: '—',
                'Tingkat (Level)' => Wilayah::labelLevel((string) $model->level),
                'Kode Wilayah' => $model->kode ?: '—',
                'Lokasi Lengkap' => $model->alamatLengkap(),
                'Status Operasional' => $model->is_active ? 'Aktif' : 'Nonaktif',
            ];
        }

        if ($model instanceof User) {
            $wilayah = $model->id_wilayah ? Wilayah::withoutGlobalScopes()->find($model->id_wilayah) : null;

            return [
                'Nama Akun' => $model->name ?: '—',
                'Username' => $model->username ?: '—',
                'Peran (Role)' => $model->role instanceof Role ? $model->role->label() : (string) $model->role,
                'Wilayah Penugasan' => $wilayah ? $wilayah->alamatLengkap() : 'Seluruh Wilayah',
                'Status Akun' => $model->is_active ? 'Aktif' : 'Nonaktif',
            ];
        }

        return [];
    }

    /**
     * Membangun payload detail saat event model terjadi di AuditObserver.
     *
     * @param  array<string, string>  $sebelum
     * @return array<string, mixed>
     */
    public static function buildPayload(Model $model, string $aksi, array $sebelum = []): array
    {
        $sesudah = self::snapshotModel($model);
        $konteks = self::extractContext($model);
        $perubahan = [];
        $kalimat = [];

        $namaSubjek = $konteks['nama_subjek'] ?? 'Data';
        $lokasi = $konteks['lokasi_singkat'] ?? '';
        $ketLokasi = $lokasi !== '' ? " di wilayah {$lokasi}" : '';

        if ($aksi === 'created') {
            $kalimat[] = match ($model->getTable()) {
                'keluarga' => "Adanya penambahan Kartu Keluarga baru ({$namaSubjek}){$ketLokasi}.",
                'anggota' => sprintf(
                    "Adanya penambahan Anggota Warga baru '%s' (%s) pada keluarga %s%s.",
                    $sesudah['Nama Lengkap'] ?? $namaSubjek,
                    $sesudah['Hubungan Keluarga'] ?? 'Anggota',
                    $sesudah['Kartu Keluarga (Kepala)'] ?? '—',
                    $ketLokasi
                ),
                'aset_keluarga' => sprintf(
                    "Adanya penambahan Aset Keluarga '%s'%s pada keluarga %s%s.",
                    $sesudah['Jenis Aset / Fasilitas'] ?? '—',
                    ($sesudah['Keterangan Aset'] ?? '—') !== '—' ? " ({$sesudah['Keterangan Aset']})" : '',
                    $sesudah['Milik Keluarga (Kepala KK)'] ?? '—',
                    $ketLokasi
                ),
                'ekonomi_keluarga' => sprintf(
                    "Adanya penambahan Data Ekonomi keluarga '%s' (Tahun %s) pada keluarga %s%s.",
                    $sesudah['Kategori Ekonomi'] ?? '—',
                    $sesudah['Tahun Data'] ?? '—',
                    $sesudah['Milik Keluarga (Kepala KK)'] ?? '—',
                    $ketLokasi
                ),
                'wilayah' => sprintf(
                    "Adanya penambahan wilayah %s baru '%s' (%s).",
                    $sesudah['Tingkat (Level)'] ?? 'Wilayah',
                    $sesudah['Nama Wilayah'] ?? $namaSubjek,
                    $sesudah['Lokasi Lengkap'] ?? '—'
                ),
                'users' => sprintf(
                    "Adanya penambahan akun pengguna baru '%s' dengan peran '%s' (%s).",
                    $sesudah['Nama Akun'] ?? $namaSubjek,
                    $sesudah['Peran (Role)'] ?? '—',
                    $sesudah['Wilayah Penugasan'] ?? '—'
                ),
                default => "Adanya penambahan data {$namaSubjek}{$ketLokasi}.",
            };
        } elseif ($aksi === 'updated') {
            foreach ($sesudah as $label => $nilaiBaru) {
                $nilaiLama = $sebelum[$label] ?? '—';
                if ($nilaiLama !== $nilaiBaru) {
                    $perubahan[] = [
                        'label' => $label,
                        'sebelum' => $nilaiLama,
                        'sesudah' => $nilaiBaru,
                    ];
                    $kalimat[] = self::kalimatPerubahan($model->getTable(), $namaSubjek, $label, $nilaiLama, $nilaiBaru, $lokasi);
                }
            }

            // Cek jika ada field profil yang tadinya ada lalu dikosongkan
            foreach ($sebelum as $label => $nilaiLama) {
                if (! array_key_exists($label, $sesudah) && $nilaiLama !== '—') {
                    $perubahan[] = [
                        'label' => $label,
                        'sebelum' => $nilaiLama,
                        'sesudah' => '— (Dikosongkan)',
                    ];
                    $kalimat[] = self::kalimatPerubahan($model->getTable(), $namaSubjek, $label, $nilaiLama, '— (Dikosongkan)', $lokasi);
                }
            }

            if ($kalimat === []) {
                $kalimat[] = "Data {$konteks['jenis_objek']} '{$namaSubjek}'{$ketLokasi} diperbarui.";
            }
        } elseif ($aksi === 'deleted') {
            $kalimat[] = match ($model->getTable()) {
                'keluarga' => "Kartu Keluarga ({$namaSubjek}){$ketLokasi} telah dihapus dan dipindahkan ke dalam Arsip.",
                'anggota' => sprintf(
                    "Anggota Warga '%s' (%s) telah dihapus / dikeluarkan dari KK %s%s dan dipindahkan ke Arsip.",
                    $sebelum['Nama Lengkap'] ?? $sesudah['Nama Lengkap'] ?? $namaSubjek,
                    $sebelum['Hubungan Keluarga'] ?? $sesudah['Hubungan Keluarga'] ?? 'Anggota',
                    $sebelum['Kartu Keluarga (Kepala)'] ?? $sesudah['Kartu Keluarga (Kepala)'] ?? '—',
                    $ketLokasi
                ),
                'aset_keluarga' => sprintf(
                    "Aset Keluarga '%s' (%s) dihapus dari keluarga %s%s.",
                    $sebelum['Jenis Aset / Fasilitas'] ?? $sesudah['Jenis Aset / Fasilitas'] ?? '—',
                    $sebelum['Keterangan Aset'] ?? $sesudah['Keterangan Aset'] ?? '—',
                    $sebelum['Milik Keluarga (Kepala KK)'] ?? $sesudah['Milik Keluarga (Kepala KK)'] ?? '—',
                    $ketLokasi
                ),
                'ekonomi_keluarga' => sprintf(
                    "Data Ekonomi '%s' (Tahun %s) dihapus dari keluarga %s%s.",
                    $sebelum['Kategori Ekonomi'] ?? $sesudah['Kategori Ekonomi'] ?? '—',
                    $sebelum['Tahun Data'] ?? $sesudah['Tahun Data'] ?? '—',
                    $sebelum['Milik Keluarga (Kepala KK)'] ?? $sesudah['Milik Keluarga (Kepala KK)'] ?? '—',
                    $ketLokasi
                ),
                'wilayah' => "Wilayah '{$namaSubjek}' ({$lokasi}) telah dihapus.",
                'users' => "Akun pengguna '{$namaSubjek}' telah dihapus.",
                default => "Data {$konteks['jenis_objek']} '{$namaSubjek}'{$ketLokasi} telah dihapus / diarsipkan.",
            };
        } elseif ($aksi === 'restored') {
            $kalimat[] = match ($model->getTable()) {
                'keluarga' => "Kartu Keluarga ({$namaSubjek}){$ketLokasi} telah dipulihkan kembali dari Arsip menjadi aktif.",
                'anggota' => sprintf(
                    "Anggota Warga '%s' (%s) telah dipulihkan kembali dari Arsip ke dalam KK %s%s.",
                    $sesudah['Nama Lengkap'] ?? $namaSubjek,
                    $sesudah['Hubungan Keluarga'] ?? 'Anggota',
                    $sesudah['Kartu Keluarga (Kepala)'] ?? '—',
                    $ketLokasi
                ),
                default => "Data {$konteks['jenis_objek']} '{$namaSubjek}'{$ketLokasi} telah dipulihkan kembali dari Arsip.",
            };
        }

        if (in_array($aksi, ['deleted', 'restored'], true) && $sebelum !== []) {
            foreach ($sesudah as $label => $nilaiBaru) {
                $nilaiLama = $sebelum[$label] ?? '—';
                if ($nilaiLama !== $nilaiBaru) {
                    $perubahan[] = ['label' => $label, 'sebelum' => $nilaiLama, 'sesudah' => $nilaiBaru];
                }
            }
        }

        return [
            'ringkasan' => $kalimat[0] ?? "Aktivitas {$aksi} pada {$namaSubjek}",
            'kalimat' => $kalimat,
            'perubahan' => $perubahan,
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'konteks' => $konteks,
        ];
    }

    /**
     * Menyusun kalimat template perubahan yang spesifik untuk tiap modul & kolom.
     */
    private static function kalimatPerubahan(string $tabel, string $namaSubjek, string $label, string $lama, string $baru, string $lokasi): string
    {
        $ketLokasi = $lokasi !== '' ? " (Wilayah: {$lokasi})" : '';

        return match ($tabel) {
            'keluarga' => match ($label) {
                'Status Kunci Data' => "Status kunci Kartu Keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Wilayah RT / RW' => "Wilayah penugasan Kartu Keluarga ({$namaSubjek}) dipindahkan dari '{$lama}' ke '{$baru}'.",
                'Alamat Domisili' => "Alamat domisili keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Alamat KTP' => "Alamat KTP keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                default => "{$label} pada Kartu Keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
            },
            'anggota' => match ($label) {
                'Status Kependudukan' => "Status kependudukan anggota '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Status Perkawinan' => "Status perkawinan anggota '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Hubungan Keluarga' => "Hubungan dalam keluarga untuk '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Pendidikan' => "Pendidikan terakhir anggota '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Pekerjaan' => "Pekerjaan anggota '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Penghasilan / Bulan' => "Penghasilan bulanan anggota '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                default => "{$label} anggota '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
            },
            'aset_keluarga' => match ($label) {
                'Jenis Aset / Fasilitas' => "Jenis aset keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Keterangan Aset' => "Keterangan aset ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                default => "{$label} aset keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
            },
            'ekonomi_keluarga' => match ($label) {
                'Kategori Ekonomi' => "Status / kategori ekonomi keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Tahun Data' => "Tahun data ekonomi keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                default => "{$label} ekonomi keluarga ({$namaSubjek}) diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
            },
            'wilayah' => match ($label) {
                'Status Operasional' => "Status operasional wilayah '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                'Nama Wilayah' => "Nama wilayah diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
                default => "{$label} wilayah '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'.",
            },
            'users' => match ($label) {
                'Peran (Role)' => "Peran akun pengguna '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'.",
                'Status Akun' => "Status akun pengguna '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'.",
                'Wilayah Penugasan' => "Wilayah penugasan akun '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'.",
                default => "{$label} akun pengguna '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'.",
            },
            default => "{$label} pada '{$namaSubjek}' diubah dari '{$lama}' ke '{$baru}'{$ketLokasi}.",
        };
    }

    /**
     * Mengekstrak informasi konteks (id_keluarga, id_wilayah, nama_subjek, lokasi_singkat).
     *
     * @return array<string, mixed>
     */
    public static function extractContext(Model $model): array
    {
        if ($model instanceof Keluarga) {
            $wilayah = Wilayah::withoutGlobalScopes()->find($model->id_wilayah);
            $kepala = Anggota::withoutGlobalScopes()->withTrashed()
                ->where('id_keluarga', $model->id)
                ->where('id_hubungan', RefHubungan::KEPALA)
                ->first();

            return [
                'jenis_objek' => 'Keluarga',
                'nama_subjek' => $kepala ? "KK {$kepala->nama_lengkap}" : ($model->no_kk_masked ?? "KK #{$model->id}"),
                'id_keluarga' => $model->id,
                'id_anggota' => $kepala?->id,
                'id_wilayah' => $model->id_wilayah,
                'lokasi_singkat' => $wilayah ? $wilayah->alamatLengkap() : '',
            ];
        }

        if ($model instanceof Anggota) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($model->id_keluarga);
            $wilayah = $keluarga ? Wilayah::withoutGlobalScopes()->find($keluarga->id_wilayah) : null;

            return [
                'jenis_objek' => 'Anggota Warga',
                'nama_subjek' => $model->nama_lengkap ?: "Anggota #{$model->id}",
                'id_keluarga' => $model->id_keluarga,
                'id_anggota' => $model->id,
                'id_wilayah' => $keluarga?->id_wilayah,
                'lokasi_singkat' => $wilayah ? $wilayah->alamatLengkap() : '',
            ];
        }

        if ($model instanceof AsetKeluarga) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($model->id_keluarga);
            $wilayah = $keluarga ? Wilayah::withoutGlobalScopes()->find($keluarga->id_wilayah) : null;
            $kepala = $keluarga
                ? Anggota::withoutGlobalScopes()->withTrashed()->where('id_keluarga', $keluarga->id)->where('id_hubungan', RefHubungan::KEPALA)->first()
                : null;

            return [
                'jenis_objek' => 'Aset Keluarga',
                'nama_subjek' => ($model->jenis_aset ?: 'Aset').($kepala ? " — KK {$kepala->nama_lengkap}" : ''),
                'id_keluarga' => $model->id_keluarga,
                'id_wilayah' => $keluarga?->id_wilayah,
                'lokasi_singkat' => $wilayah ? $wilayah->alamatLengkap() : '',
            ];
        }

        if ($model instanceof EkonomiKeluarga) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($model->id_keluarga);
            $wilayah = $keluarga ? Wilayah::withoutGlobalScopes()->find($keluarga->id_wilayah) : null;
            $kepala = $keluarga
                ? Anggota::withoutGlobalScopes()->withTrashed()->where('id_keluarga', $keluarga->id)->where('id_hubungan', RefHubungan::KEPALA)->first()
                : null;

            return [
                'jenis_objek' => 'Ekonomi Keluarga',
                'nama_subjek' => $kepala ? "KK {$kepala->nama_lengkap}" : "KK #{$model->id_keluarga}",
                'id_keluarga' => $model->id_keluarga,
                'id_wilayah' => $keluarga?->id_wilayah,
                'lokasi_singkat' => $wilayah ? $wilayah->alamatLengkap() : '',
            ];
        }

        if ($model instanceof Wilayah) {
            return [
                'jenis_objek' => 'Wilayah',
                'nama_subjek' => $model->nama ?: "Wilayah #{$model->id}",
                'id_wilayah' => $model->id,
                'lokasi_singkat' => $model->alamatLengkap(),
            ];
        }

        if ($model instanceof User) {
            $wilayah = $model->id_wilayah ? Wilayah::withoutGlobalScopes()->find($model->id_wilayah) : null;

            return [
                'jenis_objek' => 'Akun Pengguna',
                'nama_subjek' => $model->name ?: ($model->username ?: "User #{$model->id}"),
                'id_user' => $model->id,
                'id_wilayah' => $model->id_wilayah,
                'lokasi_singkat' => $wilayah ? $wilayah->alamatLengkap() : '',
            ];
        }

        return [
            'jenis_objek' => ucfirst($model->getTable()),
            'nama_subjek' => "#{$model->getKey()}",
            'lokasi_singkat' => '',
        ];
    }

    /**
     * Menghasilkan deskripsi lengkap untuk ditampilkan pada tabel & modal Log Aktivitas,
     * baik untuk log baru (yang sudah punya kolom detail) maupun log lama (fallback).
     *
     * @return array{
     *   ringkasan: string,
     *   kalimat: list<string>,
     *   perubahan: list<array{label: string, sebelum: string, sesudah: string}>,
     *   sebelum: array<string, string>,
     *   sesudah: array<string, string>,
     *   konteks: array<string, mixed>,
     *   shortcuts: list<array{label: string, url: string, warna: string, ikon: string}>,
     *   status_arsip: string
     * }
     */
    public static function describe(AuditLog $record): array
    {
        $saved = $record->detail_data;
        if (! empty($saved) && isset($saved['kalimat'])) {
            $konteks = $saved['konteks'] ?? [];
            $shortcuts = self::buildShortcuts($record, $konteks);
            $perubahanTersimpan = array_values((array) ($saved['perubahan'] ?? []));
            $kalimatTersimpan = array_values((array) ($saved['kalimat'] ?? []));
            $ringkasanTersimpan = (string) ($saved['ringkasan'] ?? $kalimatTersimpan[0] ?? '—');

            if ($record->aksi === 'updated' && $perubahanTersimpan === []) {
                $ringkasanTersimpan = 'Tidak ada nilai yang berubah — data ini hanya ikut tersimpan ulang saat form Kartu Keluarga disimpan.';
                $kalimatTersimpan = [$ringkasanTersimpan];
            }

            return [
                'ringkasan' => $ringkasanTersimpan,
                'kalimat' => $kalimatTersimpan,
                'perubahan' => $perubahanTersimpan,
                'sebelum' => (array) ($saved['sebelum'] ?? []),
                'sesudah' => (array) ($saved['sesudah'] ?? []),
                'konteks' => $konteks,
                'shortcuts' => $shortcuts['links'],
                'status_arsip' => $shortcuts['status_arsip'],
            ];
        }

        // Fallback untuk baris audit_log lama (sebelum kolom detail ada) atau event login
        if ($record->aksi === 'login') {
            $user = $record->pengguna ?? User::find($record->id_record);
            $nama = $user?->name ?? 'Pengguna';
            $peran = $user?->role instanceof Role ? $user->role->label() : 'Pengguna';
            $wilayah = $user?->wilayah ? $user->wilayah->alamatLengkap() : 'Seluruh Wilayah';
            $kal = "Pengguna '{$nama}' ({$peran}) berhasil masuk (login) ke dalam sistem.";

            return [
                'ringkasan' => $kal,
                'kalimat' => [$kal],
                'perubahan' => [],
                'sebelum' => [],
                'sesudah' => [
                    'Nama Pengguna' => $nama,
                    'Peran' => $peran,
                    'Wilayah Penugasan' => $wilayah,
                ],
                'konteks' => ['jenis_objek' => 'Login Sistem', 'nama_subjek' => $nama, 'lokasi_singkat' => $wilayah],
                'shortcuts' => [],
                'status_arsip' => 'Aktif',
            ];
        }

        $targetModel = self::findTargetModel($record->nama_tabel, (int) $record->id_record);

        if ($targetModel === null) {
            $labelModul = self::labelTabel($record->nama_tabel);
            $kal = match ($record->aksi) {
                'created' => "Adanya penambahan data {$labelModul} (ID #{$record->id_record}).",
                'updated' => "Perubahan data pada {$labelModul} (ID #{$record->id_record}).",
                'deleted' => "Penghapusan / pengarsipan data {$labelModul} (ID #{$record->id_record}).",
                'restored' => "Pemulihan data {$labelModul} (ID #{$record->id_record}) dari arsip.",
                default => "Aktivitas {$record->aksi} pada {$labelModul} (ID #{$record->id_record}).",
            };

            return [
                'ringkasan' => $kal,
                'kalimat' => [$kal],
                'perubahan' => [],
                'sebelum' => [],
                'sesudah' => [],
                'konteks' => ['jenis_objek' => $labelModul, 'nama_subjek' => "#{$record->id_record}", 'lokasi_singkat' => ''],
                'shortcuts' => [
                    [
                        'label' => 'Buka Daftar & Arsip KK',
                        'url' => KeluargaResource::getUrl('index'),
                        'warna' => 'gray',
                        'ikon' => 'heroicon-o-archive-box',
                    ],
                ],
                'status_arsip' => 'Data telah dihapus permanen atau tidak ditemukan',
            ];
        }

        $sesudah = self::snapshotModel($targetModel);
        $konteks = self::extractContext($targetModel);
        // Log lama (sebelum fitur rincian) tidak menyimpan versi sebelum diubah,
        // jadi tidak ditebak agar tidak menampilkan data arsip yang keliru.
        $payload = self::buildPayload($targetModel, $record->aksi, $sesudah);
        $sebelum = [];
        $payload['perubahan'] = [];

        // Jika log lama bertipe updated dan nilai saat ini sama dengan rekonstruksi, berikan kalimat deskriptif kondisi data
        if ($record->aksi === 'updated' && empty($payload['perubahan'])) {
            $namaSubjek = $konteks['nama_subjek'] ?? 'Data';
            $lokasi = $konteks['lokasi_singkat'] ? " di wilayah {$konteks['lokasi_singkat']}" : '';

            $kalimatDeskriptif = match ($record->nama_tabel) {
                'keluarga' => sprintf(
                    "Data Kartu Keluarga (%s)%s diperbarui — Status Kunci: '%s', Alamat Domisili: '%s', Status KK: '%s'.",
                    $namaSubjek,
                    $lokasi,
                    $sesudah['Status Kunci Data'] ?? 'Terbuka',
                    $sesudah['Alamat Domisili'] ?? '—',
                    $sesudah['Status Arsip KK'] ?? 'Aktif'
                ),
                'anggota' => sprintf(
                    "Data Anggota Warga '%s' (%s) pada %s%s diperbarui — Status Kependudukan: '%s', Status Kawin: '%s', Pendidikan: '%s', Pekerjaan: '%s'.",
                    $sesudah['Nama Lengkap'] ?? $namaSubjek,
                    $sesudah['Hubungan Keluarga'] ?? 'Anggota',
                    $sesudah['Kartu Keluarga (Kepala)'] ?? 'KK',
                    $lokasi,
                    $sesudah['Status Kependudukan'] ?? 'Menetap (Aktif)',
                    $sesudah['Status Perkawinan'] ?? '—',
                    $sesudah['Pendidikan'] ?? '—',
                    $sesudah['Pekerjaan'] ?? '—'
                ),
                'aset_keluarga' => sprintf(
                    "Data Aset Keluarga '%s' (%s) pada %s%s diperbarui.",
                    $sesudah['Jenis Aset / Fasilitas'] ?? '—',
                    $sesudah['Keterangan Aset'] ?? '—',
                    $sesudah['Milik Keluarga (Kepala KK)'] ?? '—',
                    $lokasi
                ),
                'ekonomi_keluarga' => sprintf(
                    "Data Ekonomi Keluarga pada %s%s diperbarui menjadi kategori '%s' (Tahun %s).",
                    $sesudah['Milik Keluarga (Kepala KK)'] ?? '—',
                    $lokasi,
                    $sesudah['Kategori Ekonomi'] ?? '—',
                    $sesudah['Tahun Data'] ?? '—'
                ),
                'wilayah' => sprintf(
                    "Data Wilayah %s '%s' (%s) diperbarui — Status Operasional saat ini: '%s'.",
                    $sesudah['Tingkat (Level)'] ?? '',
                    $sesudah['Nama Wilayah'] ?? $namaSubjek,
                    $sesudah['Lokasi Lengkap'] ?? '—',
                    $sesudah['Status Operasional'] ?? 'Aktif'
                ),
                'users' => sprintf(
                    "Data Akun Pengguna '%s' (%s) diperbarui — Wilayah Penugasan: '%s', Status: '%s'.",
                    $sesudah['Nama Akun'] ?? $namaSubjek,
                    $sesudah['Peran (Role)'] ?? '—',
                    $sesudah['Wilayah Penugasan'] ?? '—',
                    $sesudah['Status Akun'] ?? 'Aktif'
                ),
                default => $payload['ringkasan'],
            };

            $payload['ringkasan'] = $kalimatDeskriptif;
            $payload['kalimat'] = [$kalimatDeskriptif];
        }

        $shortcuts = self::buildShortcuts($record, $konteks);

        return [
            'ringkasan' => (string) $payload['ringkasan'],
            'kalimat' => $payload['kalimat'],
            'perubahan' => $payload['perubahan'],
            'sebelum' => $sebelum,
            'sesudah' => $sesudah,
            'konteks' => $konteks,
            'shortcuts' => $shortcuts['links'],
            'status_arsip' => $shortcuts['status_arsip'],
        ];
    }

    /**
     * Untuk log lama sebelum kolom detail ada, rekonstruksi tampilan "Versi Sebelumnya / Awal"
     * agar bagian Arsip Versi Sebelum Diubah tetap informatif.
     *
     * @param  array<string, string>  $sesudah
     * @return array<string, string>
     */
    private static function reconstructFallbackPreviousSnapshot(Model $model, array $sesudah): array
    {
        $sebelum = $sesudah;

        if ($model instanceof Anggota) {
            if (($sesudah['Status Kependudukan'] ?? '') === 'Pindah') {
                $sebelum['Status Kependudukan'] = 'Menetap (Aktif)';
            } elseif (($sesudah['Status Kependudukan'] ?? '') === 'Meninggal Dunia') {
                $sebelum['Status Kependudukan'] = 'Menetap (Aktif)';
            }
            if (($sesudah['Status Perkawinan'] ?? '') === 'Cerai hidup' || ($sesudah['Status Perkawinan'] ?? '') === 'Cerai mati') {
                $sebelum['Status Perkawinan'] = 'Kawin';
            }
            if ($model->trashed()) {
                $sebelum['Status di KK'] = 'Aktif di KK';
            }
        } elseif ($model instanceof Keluarga) {
            if ($model->isLocked()) {
                $sebelum['Status Kunci Data'] = 'Terbuka';
            }
            if ($model->trashed()) {
                $sebelum['Status Arsip KK'] = 'Aktif';
            }
        } elseif ($model instanceof Wilayah) {
            $sebelum['Status Operasional'] = $model->is_active ? 'Nonaktif' : 'Aktif';
        }

        return $sebelum;
    }

    /**
     * Menyusun daftar tombol pintasan (shortcut) ke halaman Detail, Arsip KK, atau Wilayah.
     *
     * @param  array<string, mixed>  $konteks
     * @return array{links: list<array{label: string, url: string, warna: string, ikon: string}>, status_arsip: string}
     */
    private static function buildShortcuts(AuditLog $record, array $konteks): array
    {
        $links = [];
        $statusArsip = 'Data Aktif';

        $idKeluarga = $konteks['id_keluarga'] ?? ($record->nama_tabel === 'keluarga' ? $record->id_record : null);
        $idAnggota = $konteks['id_anggota'] ?? ($record->nama_tabel === 'anggota' ? $record->id_record : null);
        $idWilayah = $konteks['id_wilayah'] ?? ($record->nama_tabel === 'wilayah' ? $record->id_record : null);

        if ($idKeluarga) {
            $keluarga = Keluarga::withoutGlobalScopes()->withTrashed()->find($idKeluarga);
            if ($keluarga) {
                if ($keluarga->trashed()) {
                    $statusArsip = 'Kartu Keluarga berada di Arsip (Terhapus)';
                    $links[] = [
                        'label' => 'Buka Arsip KK (Pulihkan / Lihat)',
                        'url' => KeluargaResource::getUrl('index', ['tableFilters' => ['trashed' => ['value' => '1']]]),
                        'warna' => 'warning',
                        'ikon' => 'heroicon-o-archive-box',
                    ];
                } else {
                    $adaAnggotaArsip = $keluarga->anggota()->onlyTrashed()->exists();
                    if ($adaAnggotaArsip) {
                        $statusArsip = 'KK Aktif · Memiliki Anggota di Arsip KK';
                    }

                    $links[] = [
                        'label' => $adaAnggotaArsip ? 'Buka Detail & Arsip Anggota KK' : 'Buka Detail Kartu Keluarga (KK)',
                        'url' => KeluargaResource::getUrl('view', ['record' => $keluarga->id]),
                        'warna' => 'primary',
                        'ikon' => 'heroicon-o-home-modern',
                    ];

                    $links[] = [
                        'label' => 'Ubah Data / Anggota KK',
                        'url' => KeluargaResource::getUrl('edit', ['record' => $keluarga->id]),
                        'warna' => 'gray',
                        'ikon' => 'heroicon-o-pencil-square',
                    ];
                }
            }
        }

        if ($idAnggota) {
            $anggota = Anggota::withoutGlobalScopes()->withTrashed()->find($idAnggota);
            if ($anggota) {
                if ($anggota->trashed()) {
                    $statusArsip = 'Anggota ini telah diarsipkan (Dihapus / Keluar dari KK)';
                } elseif (($anggota->status_kependudukan ?? 'menetap') === 'pindah') {
                    $statusArsip = 'Anggota berstatus Pindah Domisili';
                } elseif (($anggota->status_kependudukan ?? 'menetap') === 'meninggal') {
                    $statusArsip = 'Anggota berstatus Meninggal Dunia';
                }

                if (! $anggota->trashed()) {
                    $links[] = [
                        'label' => 'Buka Profil Detail Anggota',
                        'url' => AnggotaResource::getUrl('view', ['record' => $anggota->id]),
                        'warna' => 'info',
                        'ikon' => 'heroicon-o-user',
                    ];
                }
            }
        }

        if ($idWilayah) {
            $wilayah = Wilayah::withoutGlobalScopes()->find($idWilayah);
            if ($wilayah) {
                $links[] = [
                    'label' => "Buka Kelola Warga ({$wilayah->nama})",
                    'url' => JelajahWilayah::getUrl(['wilayah' => $wilayah->id]),
                    'warna' => 'gray',
                    'ikon' => 'heroicon-o-map-pin',
                ];
            }
        }

        $links[] = [
            'label' => 'Buka Daftar & Arsip Semua KK',
            'url' => KeluargaResource::getUrl('index', ['tableFilters' => ['trashed' => ['value' => '1']]]),
            'warna' => 'warning',
            'ikon' => 'heroicon-o-archive-box-arrow-down',
        ];

        return [
            'links' => $links,
            'status_arsip' => $statusArsip,
        ];
    }

    public static function findTargetModel(string $namaTabel, int $idRecord): ?Model
    {
        return match ($namaTabel) {
            'keluarga' => Keluarga::withoutGlobalScopes()->withTrashed()->find($idRecord),
            'anggota' => Anggota::withoutGlobalScopes()->withTrashed()->find($idRecord),
            'aset_keluarga' => AsetKeluarga::withoutGlobalScopes()->find($idRecord),
            'ekonomi_keluarga' => EkonomiKeluarga::withoutGlobalScopes()->find($idRecord),
            'wilayah' => Wilayah::withoutGlobalScopes()->find($idRecord),
            'users' => User::withoutGlobalScopes()->find($idRecord),
            default => null,
        };
    }

    public static function labelStatusKependudukan(?string $status): string
    {
        return match ($status) {
            'pindah' => 'Pindah',
            'meninggal' => 'Meninggal Dunia',
            default => 'Menetap (Aktif)',
        };
    }

    public static function labelStatusKawin(?string $status): string
    {
        return match ($status) {
            'belum_kawin' => 'Belum kawin',
            'kawin' => 'Kawin',
            'cerai_hidup' => 'Cerai hidup',
            'cerai_mati' => 'Cerai mati',
            default => $status ?: '—',
        };
    }

    public static function labelTabel(string $tabel): string
    {
        return match ($tabel) {
            'keluarga' => 'Keluarga',
            'anggota' => 'Anggota Warga',
            'users' => 'Akun Pengguna',
            'wilayah' => 'Wilayah',
            'aset_keluarga' => 'Aset Keluarga',
            'ekonomi_keluarga' => 'Ekonomi Keluarga',
            default => ucfirst($tabel),
        };
    }
}

