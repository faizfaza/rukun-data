<?php

namespace App\Models;

use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class ArsipSnapshotDesa extends Model
{
    protected $table = 'arsip_snapshot_desa';

    protected $fillable = [
        'id_wilayah_desa',
        'kode_snapshot',
        'interval_jam',
        'jumlah_keluarga',
        'jumlah_warga',
        'jumlah_menetap',
        'jumlah_pindah',
        'jumlah_perubahan',
        'ringkasan_statistik',
        'data_snapshot',
    ];

    protected $hidden = ['data_snapshot'];

    protected function casts(): array
    {
        return [
            'ringkasan_statistik' => 'array',
        ];
    }

    public function setDataSnapshotAttribute(mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->attributes['data_snapshot'] = null;

            return;
        }

        $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $this->attributes['data_snapshot'] = $json ? SensitiveData::encrypt($json) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshotDataAttribute(): array
    {
        $raw = $this->attributes['data_snapshot'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decrypted = SensitiveData::decrypt($raw);
        if (! is_string($decrypted) || $decrypted === '') {
            return [];
        }

        $decoded = json_decode($decrypted, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function wilayahDesa(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'id_wilayah_desa');
    }

    /**
     * Mengambil ringkasan angka batch yang sudah disesuaikan dengan cakupan pengguna (atau filter desa tertentu).
     *
     * @return array{desa_label: string, desa_sub: string, jumlah_keluarga: int, jumlah_warga: int, jumlah_menetap: int, jumlah_pindah: int}
     */
    public function ringkasanUntuk(?User $user = null, ?int $filterDesaId = null): array
    {
        $targetDesaId = $filterDesaId;
        if (! $targetDesaId && $user?->isAdminDesa() && $user->id_wilayah) {
            $targetDesaId = (int) $user->id_wilayah;
        }

        $perDesa = $this->ringkasan_statistik['per_desa'] ?? [];

        if ($targetDesaId && isset($perDesa[$targetDesaId])) {
            $d = $perDesa[$targetDesaId];

            return [
                'desa_label' => 'Desa '.($d['nama_desa'] ?? ''),
                'desa_sub' => $d['kecamatan_kab'] ?? 'Cakupan Desa',
                'jumlah_keluarga' => (int) ($d['jumlah_keluarga'] ?? 0),
                'jumlah_warga' => (int) ($d['jumlah_warga'] ?? 0),
                'jumlah_menetap' => (int) ($d['jumlah_menetap'] ?? 0),
                'jumlah_pindah' => (int) ($d['jumlah_pindah'] ?? 0),
            ];
        }

        $namaDesaList = collect($perDesa)->pluck('nama_desa')->filter()->values()->all();
        if ($namaDesaList === [] && $this->wilayahDesa) {
            $namaDesaList = [$this->wilayahDesa->nama];
        }

        $jmlDesa = count($namaDesaList);
        $desaLabel = $jmlDesa > 1 ? "{$jmlDesa} Desa Aktif" : ('Desa '.($namaDesaList[0] ?? 'Aktif'));
        $desaSub = implode(', ', $namaDesaList);

        return [
            'desa_label' => $desaLabel,
            'desa_sub' => $desaSub ?: 'Seluruh Desa Aktif',
            'jumlah_keluarga' => (int) $this->jumlah_keluarga,
            'jumlah_warga' => (int) $this->jumlah_warga,
            'jumlah_menetap' => (int) $this->jumlah_menetap,
            'jumlah_pindah' => (int) $this->jumlah_pindah,
        ];
    }

    /**
     * Memeriksa dan menjalankan snapshot otomatis secara SERENTAK dalam 1 Baris Batch Global
     * setiap melewati interval jam yang ditentukan.
     */
    public static function prosesSemuaDesaJatuhTempo(): int
    {
        try {
            if (! Schema::hasTable('arsip_snapshot_desa')) {
                return 0;
            }
        } catch (\Throwable) {
            return 0;
        }

        $desaIds = Wilayah::idDesaKlienAktif();
        if ($desaIds === []) {
            return 0;
        }

        $daftarDesa = Wilayah::withoutGlobalScopes()
            ->where('level', 'desa')
            ->whereIn('id', $desaIds)
            ->orderBy('id')
            ->get();

        if ($daftarDesa->isEmpty()) {
            return 0;
        }

        $desaUtama = $daftarDesa->first();
        $intervalJam = max(1, (int) ($desaUtama?->pengaturanDesa('interval_arsip_jam', 36) ?? 36));

        // Ambil semua RT dari seluruh desa aktif
        $semuaRtIds = [];
        foreach ($daftarDesa as $desa) {
            $rtIds = Wilayah::withoutGlobalScopes()
                ->where('level', 'rt')
                ->where('path', 'like', $desa->path.'%')
                ->pluck('id')
                ->all();
            $semuaRtIds = array_merge($semuaRtIds, $rtIds);
        }
        $semuaRtIds = array_values(array_unique($semuaRtIds));

        if ($semuaRtIds === [] || Keluarga::withoutGlobalScopes()->whereIn('id_wilayah', $semuaRtIds)->count() === 0) {
            return 0;
        }

        $terakhir = self::query()->latest('created_at')->latest('id')->first();

        if ($terakhir === null) {
            $jmlAwal = self::hitungPerubahanSejak($semuaRtIds, null, 0);
            self::rekamBatchGlobal($daftarDesa->all(), $intervalJam, max(1, $jmlAwal), now());

            return 1;
        }

        // Jika masih ada baris lama yang terpecah per-desa (bukan format Batch terpadu), gabungkan otomatis
        if (! isset($terakhir->ringkasan_statistik['per_desa'])) {
            self::query()->delete();
            $jmlAwal = self::hitungPerubahanSejak($semuaRtIds, null, 0);
            self::rekamBatchGlobal($daftarDesa->all(), $intervalJam, max(1, $jmlAwal), now());

            return 1;
        }

        $waktuJatuhTempo = $terakhir->created_at->copy()->addHours($intervalJam);
        if (now()->lessThan($waktuJatuhTempo)) {
            return 0;
        }

        $lastAuditId = (int) ($terakhir->ringkasan_statistik['last_audit_id'] ?? 0);
        $jumlahPerubahan = self::hitungPerubahanSejak($semuaRtIds, $terakhir->created_at, $lastAuditId);
        if ($jumlahPerubahan <= 0) {
            return 0;
        }

        self::rekamBatchGlobal($daftarDesa->all(), $intervalJam, $jumlahPerubahan, now());

        return 1;
    }

    public static function prosesSnapshotOtomatisDesa(Wilayah $desa, mixed $waktuSinkron = null): ?self
    {
        self::prosesSemuaDesaJatuhTempo();

        return self::query()->latest('created_at')->latest('id')->first();
    }

    /**
     * @param  list<int>  $rtIds
     */
    public static function hitungPerubahanSejak(array $rtIds, mixed $sejakWaktu, int $lastAuditId = 0): int
    {
        if ($rtIds === []) {
            return 0;
        }

        $keluargaIds = Keluarga::withoutGlobalScopes()
            ->withTrashed()
            ->whereIn('id_wilayah', $rtIds)
            ->pluck('id')
            ->all();

        if ($keluargaIds === []) {
            return 0;
        }

        $query = AuditLog::query()
            ->whereIn('nama_tabel', ['keluarga', 'anggota', 'aset_keluarga', 'ekonomi_keluarga'])
            ->where(function ($q) use ($keluargaIds): void {
                $q->where(function ($sub) use ($keluargaIds): void {
                    $sub->where('nama_tabel', 'keluarga')->whereIn('id_record', $keluargaIds);
                })->orWhere(function ($sub) use ($keluargaIds): void {
                    $sub->where('nama_tabel', 'anggota')
                        ->whereIn('id_record', function ($sq) use ($keluargaIds): void {
                            $sq->select('id')->from('anggota')->whereIn('id_keluarga', $keluargaIds);
                        });
                })->orWhere(function ($sub) use ($keluargaIds): void {
                    $sub->where('nama_tabel', 'aset_keluarga')
                        ->whereIn('id_record', function ($sq) use ($keluargaIds): void {
                            $sq->select('id')->from('aset_keluarga')->whereIn('id_keluarga', $keluargaIds);
                        });
                })->orWhere(function ($sub) use ($keluargaIds): void {
                    $sub->where('nama_tabel', 'ekonomi_keluarga')
                        ->whereIn('id_record', function ($sq) use ($keluargaIds): void {
                            $sq->select('id')->from('ekonomi_keluarga')->whereIn('id_keluarga', $keluargaIds);
                        });
                });
            });

        if ($lastAuditId > 0) {
            $query->where('id', '>', $lastAuditId);
        } elseif ($sejakWaktu !== null) {
            $query->where('waktu', '>', $sejakWaktu);
        }

        return $query->count();
    }

    /**
     * Merekam 1 Batch Snapshot Global yang mencakup seluruh desa aktif dalam satu baris terpadu.
     *
     * @param  list<Wilayah>  $daftarDesa
     */
    public static function rekamBatchGlobal(array $daftarDesa, int $intervalJam, int $jumlahPerubahan, mixed $waktuSinkron = null): self
    {
        $waktuRekam = $waktuSinkron ? Carbon::parse($waktuSinkron) : now();
        $urutanBatch = self::query()->count() + 1;
        $kodeBatch = sprintf('BATCH-%03d-%s', $urutanBatch, $waktuRekam->format('Ymd-His'));
        $waktuSnapshot = $waktuRekam->format('Y-m-d H:i:s');

        $totalKeluarga = 0;
        $totalWarga = 0;
        $totalMenetap = 0;
        $totalPindah = 0;
        $totalPenghasilan = 0.0;
        $jumlahBerpenghasilan = 0;
        $totalAset = 0;

        $distribusiEkonomi = [];
        $distribusiPendidikan = [];
        $distribusiPekerjaan = [];
        $perDesaStat = [];

        $keluargaList = [];
        $datasetLatih = [];
        $wilayahSnapshot = [];
        $adminSnapshot = [];

        foreach ($daftarDesa as $desa) {
            $rtIds = Wilayah::withoutGlobalScopes()
                ->where('level', 'rt')
                ->where('path', 'like', $desa->path.'%')
                ->pluck('id')
                ->all();

            if ($rtIds === []) {
                continue;
            }

            $keluargaModels = Keluarga::withoutGlobalScopes()
                ->whereIn('id_wilayah', $rtIds)
                ->with([
                    'wilayah.'.Wilayah::RELASI_LELUHUR,
                    'anggota' => fn ($q) => $q->withoutGlobalScopes()->withTrashed()->orderBy('id_hubungan'),
                    'anggota.hubungan',
                    'anggota.agama',
                    'anggota.pendidikan',
                    'anggota.pekerjaan',
                    'aset',
                    'ekonomi',
                ])
                ->orderBy('id_wilayah')
                ->orderBy('id')
                ->get();

            if ($keluargaModels->isEmpty()) {
                continue;
            }

            $semuaWilayahDesa = Wilayah::withoutGlobalScopes()
                ->where('path', 'like', $desa->path.'%')
                ->orderBy('path')
                ->get();

            foreach ($semuaWilayahDesa as $w) {
                $wilayahSnapshot[] = [
                    'id' => $w->id,
                    'id_wilayah_desa' => $desa->id,
                    'desa' => $desa->nama,
                    'kode' => $w->kode,
                    'nama' => $w->nama,
                    'level' => strtoupper($w->level),
                    'is_active' => (bool) $w->is_active,
                ];
            }

            $admins = User::query()
                ->whereIn('id_wilayah', $semuaWilayahDesa->pluck('id'))
                ->with('wilayah')
                ->orderBy('role')
                ->get();

            foreach ($admins as $u) {
                $adminSnapshot[] = [
                    'id_wilayah_desa' => $desa->id,
                    'desa' => $desa->nama,
                    'name' => $u->name,
                    'username' => $u->username,
                    'role' => $u->role?->label() ?? (string) $u->role,
                    'wilayah' => $u->wilayah?->nama ?? '—',
                    'is_active' => (bool) $u->is_active,
                ];
            }

            $desaKk = $keluargaModels->count();
            $desaWarga = 0;
            $desaMenetap = 0;
            $desaPindah = 0;

            foreach ($keluargaModels as $k) {
                $totalKeluarga++;
                $rtNama = $k->wilayah?->nama ?? '—';
                $rwNama = $k->wilayah?->rwInduk()?->nama ?? '—';
                $alamatWilayahLengkap = $k->wilayah?->alamatLengkap() ?? '—';
                $ekonomiTerbaru = $k->ekonomi->sortByDesc('tahun_data')->first();
                $kategoriEkonomi = $ekonomiTerbaru?->kategori_ekonomi ?: 'Belum Diisi';
                $tahunEkonomi = $ekonomiTerbaru?->tahun_data;
                $distribusiEkonomi[$kategoriEkonomi] = ($distribusiEkonomi[$kategoriEkonomi] ?? 0) + 1;

                $daftarAset = $k->aset->pluck('jenis_aset')->filter()->values()->all();
                $asetLengkap = $k->aset->map(fn (AsetKeluarga $aset): array => [
                    'jenis_aset' => $aset->jenis_aset,
                    'keterangan' => $aset->keterangan ?: '—',
                ])->values()->all();

                $jumlahAsetKk = count($daftarAset);
                $totalAset += $jumlahAsetKk;

                $anggotaAktifCollection = $k->anggota->filter(fn (Anggota $a): bool => ! $a->trashed());
                $jumlahAnggotaKk = $anggotaAktifCollection->count();

                $kepala = $anggotaAktifCollection->firstWhere('id_hubungan', RefHubungan::KEPALA)
                    ?? $k->anggota->firstWhere('id_hubungan', RefHubungan::KEPALA)
                    ?? $k->anggota->first();
                $idKeluargaHash = substr(hash('sha256', 'KK-'.$k->id.'-'.($k->no_kk_hash ?? $k->id)), 0, 12);

                $anggotaSnapshot = [];

                foreach ($k->anggota as $a) {
                    $isTrashed = $a->trashed();
                    $statusWarga = $a->status_kependudukan ?: 'menetap';

                    if (! $isTrashed) {
                        $totalWarga++;
                        $desaWarga++;
                        if ($statusWarga === 'pindah') {
                            $totalPindah++;
                            $desaPindah++;
                        } else {
                            $totalMenetap++;
                            $desaMenetap++;
                        }
                    }

                    $umur = $a->tanggal_lahir ? (int) $a->tanggal_lahir->diffInYears(now()) : null;
                    $agamaNama = $a->agama?->nama ?? '—';
                    $pendidikanNama = $a->pendidikan?->nama ?? '—';
                    $pekerjaanNama = $a->pekerjaan?->nama ?? '—';
                    $hubunganNama = $a->hubungan?->nama ?? 'Anggota';
                    $penghasilan = $a->penghasilan_bulanan !== null && $a->penghasilan_bulanan !== ''
                        ? (float) $a->penghasilan_bulanan
                        : 0.0;

                    if (! $isTrashed && $penghasilan > 0) {
                        $totalPenghasilan += $penghasilan;
                        $jumlahBerpenghasilan++;
                    }

                    if (! $isTrashed) {
                        $distribusiPendidikan[$pendidikanNama] = ($distribusiPendidikan[$pendidikanNama] ?? 0) + 1;
                        $distribusiPekerjaan[$pekerjaanNama] = ($distribusiPekerjaan[$pekerjaanNama] ?? 0) + 1;
                    }

                    $idWargaHash = substr(hash('sha256', 'W-'.$a->id.'-'.($a->nik_hash ?? $a->id)), 0, 12);

                    $profilBersih = [];
                    if (is_array($a->profil)) {
                        foreach (\App\Filament\Schemas\Profil::FIELDS as $kField => $labelField) {
                            if (! empty($a->profil[$kField])) {
                                $profilBersih[$labelField] = (string) $a->profil[$kField];
                            }
                        }
                    }

                    $anggotaSnapshot[] = [
                        'id_anggota' => $a->id,
                        'nama_lengkap' => $a->nama_lengkap,
                        'nik' => $a->nik,
                        'nik_masked' => $a->nik_masked,
                        'jenis_kelamin' => $a->jenis_kelamin,
                        'tempat_lahir' => $a->tempat_lahir ?: '—',
                        'tanggal_lahir' => $a->tanggal_lahir ? $a->tanggal_lahir->format('d M Y') : '—',
                        'umur' => $umur,
                        'agama' => $agamaNama,
                        'hubungan' => $hubunganNama,
                        'status_kawin' => $a->status_kawin ?: '—',
                        'pendidikan' => $pendidikanNama,
                        'pekerjaan' => $pekerjaanNama,
                        'nama_ayah' => $a->nama_ayah ?: '—',
                        'nama_ibu' => $a->nama_ibu ?: '—',
                        'penghasilan_bulanan' => $penghasilan,
                        'status_kependudukan' => $statusWarga,
                        'is_trashed' => $isTrashed,
                        'diarsipkan_pada' => $isTrashed && $a->deleted_at ? $a->deleted_at->format('d M Y H:i') : null,
                        'profil' => $profilBersih,
                    ];

                    if (! $isTrashed) {
                        $datasetLatih[] = [
                            'id_wilayah_desa' => $desa->id,
                            'desa' => $desa->nama,
                            'kode_snapshot' => $kodeBatch,
                            'waktu_snapshot' => $waktuSnapshot,
                            'id_keluarga_hash' => $idKeluargaHash,
                            'no_kk_masked' => $k->no_kk_masked,
                            'id_warga_hash' => $idWargaHash,
                            'nik_masked' => $a->nik_masked,
                            'wilayah_rt' => $rtNama,
                            'wilayah_rw' => $rwNama,
                            'jenis_kelamin' => $a->jenis_kelamin,
                            'tempat_lahir' => $a->tempat_lahir ?? '',
                            'tanggal_lahir' => $a->tanggal_lahir ? $a->tanggal_lahir->format('Y-m-d') : '',
                            'umur' => $umur ?? 0,
                            'agama' => $agamaNama,
                            'status_kawin' => $a->status_kawin ?? 'belum_kawin',
                            'hubungan_keluarga' => $hubunganNama,
                            'pendidikan' => $pendidikanNama,
                            'pekerjaan' => $pekerjaanNama,
                            'nama_ayah' => $a->nama_ayah ?? '',
                            'nama_ibu' => $a->nama_ibu ?? '',
                            'penghasilan_bulanan' => (int) round($penghasilan),
                            'jumlah_anggota_kk' => $jumlahAnggotaKk,
                            'jumlah_aset_kk' => $jumlahAsetKk,
                            'jenis_aset_kk' => implode('; ', $daftarAset),
                            'status_kependudukan' => $statusWarga,
                            'label_kategori_ekonomi' => $kategoriEkonomi,
                        ];
                    }
                }

                $keluargaList[] = [
                    'id_wilayah_desa' => $desa->id,
                    'desa' => $desa->nama,
                    'id_keluarga' => $k->id,
                    'no_kk' => $k->no_kk,
                    'no_kk_masked' => $k->no_kk_masked,
                    'kepala_keluarga' => $kepala?->nama_lengkap ?? '—',
                    'rt' => $rtNama,
                    'rw' => $rwNama,
                    'wilayah_lengkap' => $alamatWilayahLengkap,
                    'alamat' => $k->alamat ?: '—',
                    'alamat_ktp' => $k->alamat_ktp ?: ($k->alamat ?: '—'),
                    'kode_pos' => $k->kode_pos ?: '—',
                    'is_locked' => (bool) $k->is_locked,
                    'kategori_ekonomi' => $kategoriEkonomi,
                    'tahun_ekonomi' => $tahunEkonomi,
                    'aset' => $daftarAset,
                    'aset_lengkap' => $asetLengkap,
                    'jumlah_anggota' => $jumlahAnggotaKk,
                    'anggota' => $anggotaSnapshot,
                ];
            }

            $perDesaStat[$desa->id] = [
                'id_desa' => $desa->id,
                'nama_desa' => $desa->nama,
                'kecamatan_kab' => $desa->rantaiIndukNama(),
                'jumlah_keluarga' => $desaKk,
                'jumlah_warga' => $desaWarga,
                'jumlah_menetap' => $desaMenetap,
                'jumlah_pindah' => $desaPindah,
            ];
        }

        $desaUtamaId = $daftarDesa[0]->id;

        $ringkasanStatistik = [
            'urutan_periode' => $urutanBatch,
            'per_desa' => $perDesaStat,
            'kategori_ekonomi' => $distribusiEkonomi,
            'pendidikan' => $distribusiPendidikan,
            'pekerjaan' => $distribusiPekerjaan,
            'rata_penghasilan' => $jumlahBerpenghasilan > 0 ? (int) round($totalPenghasilan / $jumlahBerpenghasilan) : 0,
            'total_aset_tercatat' => $totalAset,
            'last_audit_id' => (int) AuditLog::max('id'),
        ];

        $record = self::create([
            'id_wilayah_desa' => $desaUtamaId,
            'kode_snapshot' => $kodeBatch,
            'interval_jam' => $intervalJam,
            'jumlah_keluarga' => $totalKeluarga,
            'jumlah_warga' => $totalWarga,
            'jumlah_menetap' => $totalMenetap,
            'jumlah_pindah' => $totalPindah,
            'jumlah_perubahan' => $jumlahPerubahan,
            'ringkasan_statistik' => $ringkasanStatistik,
            'data_snapshot' => [
                'desa' => implode(', ', array_column($perDesaStat, 'nama_desa')),
                'waktu_snapshot' => $waktuSnapshot,
                'wilayah_list' => $wilayahSnapshot,
                'admin_list' => $adminSnapshot,
                'keluarga_list' => $keluargaList,
                'dataset_latih' => $datasetLatih,
            ],
        ]);

        if ($waktuSinkron !== null) {
            $record->forceFill([
                'created_at' => $waktuRekam,
                'updated_at' => $waktuRekam,
            ])->saveQuietly();
        }

        return $record;
    }

    /**
     * Kompatibilitas pemanggilan rekamSnapshotDesa untuk satu desa.
     *
     * @param  list<int>  $rtIds
     */
    public static function rekamSnapshotDesa(Wilayah $desa, array $rtIds, int $intervalJam, int $jumlahPerubahan, mixed $waktuSinkron = null): self
    {
        $desaIds = Wilayah::idDesaKlienAktif();
        $daftarDesa = Wilayah::withoutGlobalScopes()
            ->where('level', 'desa')
            ->whereIn('id', $desaIds ?: [$desa->id])
            ->orderBy('id')
            ->get()
            ->all();

        return self::rekamBatchGlobal($daftarDesa ?: [$desa], $intervalJam, $jumlahPerubahan, $waktuSinkron);
    }
}
