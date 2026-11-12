<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\AnggotaResource;
use App\Filament\Resources\KeluargaResource;
use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\Wilayah;
use App\Support\WilayahScope;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

// Ringkasan angka mengikuti scope wilayah pengguna secara otomatis (global scope).
// Rincian detail keluarga & anggota dimuat saat pengguna mengklik kartu/grafik.
class RingkasanWidget extends Widget
{
    // Urutan dashboard: kartu ringkasan angka di atas, grafik di bawah.
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.ringkasan';

    public ?string $detailTipe = null;

    public ?string $detailKunci = null;

    public string $cariDetail = '';

    // Filter Wilayah & RT / RW (sama seperti Filter Kelola Warga)
    public ?string $filterProvinsi = null;

    public ?string $filterKabkota = null;

    public ?string $filterKecamatan = null;

    public ?string $filterDesa = null;

    public ?string $filterRw = null;

    public ?string $filterRt = null;

    // Filter Umur & Rincian Data Warga
    public string $filterJk = '';

    public string $filterUmurKategori = '';

    public ?string $filterUmurMin = null;

    public ?string $filterUmurMax = null;

    public string $filterPendidikan = '';

    public string $filterPekerjaan = '';

    public string $filterHubungan = '';

    public string $filterStatusWarga = '';

    #[On('buka-detail-dashboard')]
    public function bukaDetail(string $tipe, string $kunci = 'semua'): void
    {
        $this->detailTipe = $tipe;
        $this->detailKunci = $kunci;
        $this->cariDetail = '';
        $this->resetFilterDetail();
    }

    public function pilihSubKategori(string $kunci): void
    {
        $this->detailKunci = $kunci;
    }

    public function tutupDetail(): void
    {
        $this->detailTipe = null;
        $this->detailKunci = null;
        $this->cariDetail = '';
        $this->resetFilterDetail();
    }

    public function resetFilterWilayah(): void
    {
        $this->filterProvinsi = null;
        $this->filterKabkota = null;
        $this->filterKecamatan = null;
        $this->filterDesa = null;
        $this->filterRw = null;
        $this->filterRt = null;
    }

    public function resetFilterDetail(): void
    {
        $this->resetFilterWilayah();
        $this->filterJk = '';
        $this->filterUmurKategori = '';
        $this->filterUmurMin = null;
        $this->filterUmurMax = null;
        $this->filterPendidikan = '';
        $this->filterPekerjaan = '';
        $this->filterHubungan = '';
        $this->filterStatusWarga = '';
    }

    public function hasFilterWilayah(): bool
    {
        return filled($this->filterProvinsi)
            || filled($this->filterKabkota)
            || filled($this->filterKecamatan)
            || filled($this->filterDesa)
            || filled($this->filterRw)
            || filled($this->filterRt);
    }

    public function getJumlahFilterDetailAktifProperty(): int
    {
        return (filled($this->filterProvinsi) ? 1 : 0)
            + (filled($this->filterKabkota) ? 1 : 0)
            + (filled($this->filterKecamatan) ? 1 : 0)
            + (filled($this->filterDesa) ? 1 : 0)
            + (filled($this->filterRw) ? 1 : 0)
            + (filled($this->filterRt) ? 1 : 0)
            + ($this->filterJk !== '' ? 1 : 0)
            + ($this->filterUmurKategori !== '' ? 1 : 0)
            + (filled($this->filterUmurMin) ? 1 : 0)
            + (filled($this->filterUmurMax) ? 1 : 0)
            + ($this->filterPendidikan !== '' ? 1 : 0)
            + ($this->filterPekerjaan !== '' ? 1 : 0)
            + ($this->filterHubungan !== '' ? 1 : 0)
            + ($this->filterStatusWarga !== '' ? 1 : 0);
    }

    /** @return list<string> */
    public function getDaftarProvinsiProperty(): array
    {
        if ($this->detailTipe === null) {
            return [];
        }

        return Wilayah::where('level', 'provinsi')->orderBy('nama')->pluck('nama')->all();
    }

    /** @return list<string> */
    public function getDaftarKabkotaProperty(): array
    {
        if ($this->detailTipe === null) {
            return [];
        }

        $q = Wilayah::where('level', 'kabupaten_kota');
        if (filled($this->filterProvinsi)) {
            $provIds = Wilayah::where('level', 'provinsi')
                ->where('nama', 'like', '%'.trim((string) $this->filterProvinsi).'%')
                ->pluck('id')
                ->all();
            if ($provIds !== []) {
                $q->whereIn('parent_id', $provIds);
            }
        }

        return $q->orderBy('nama')->limit(100)->pluck('nama')->all();
    }

    /** @return list<string> */
    public function getDaftarKecamatanProperty(): array
    {
        if ($this->detailTipe === null) {
            return [];
        }

        $q = Wilayah::where('level', 'kecamatan');
        if (filled($this->filterKabkota)) {
            $kabIds = Wilayah::where('level', 'kabupaten_kota')
                ->where('nama', 'like', '%'.trim((string) $this->filterKabkota).'%')
                ->limit(50)
                ->pluck('id')
                ->all();
            if ($kabIds !== []) {
                $q->whereIn('parent_id', $kabIds);
            }
        }

        return $q->orderBy('nama')->limit(50)->pluck('nama')->all();
    }

    /** @return list<string> */
    public function getDaftarDesaProperty(): array
    {
        if ($this->detailTipe === null) {
            return [];
        }

        $q = Wilayah::whereIn('level', ['desa', 'dusun']);
        if (filled($this->filterKecamatan)) {
            $kecIds = Wilayah::where('level', 'kecamatan')
                ->where('nama', 'like', '%'.trim((string) $this->filterKecamatan).'%')
                ->limit(50)
                ->pluck('id')
                ->all();
            if ($kecIds !== []) {
                $q->where(function (Builder $sub) use ($kecIds): void {
                    $sub->whereIn('parent_id', $kecIds)
                        ->orWhereHas('parent', fn ($p) => $p->whereIn('parent_id', $kecIds));
                });
            }
        }

        return $q->orderBy('nama')->limit(50)->pluck('nama')->all();
    }

    /** @return list<string> */
    public function getDaftarRwProperty(): array
    {
        if ($this->detailTipe === null) {
            return [];
        }

        $options = [];
        for ($i = 1; $i <= 30; $i++) {
            $options[] = sprintf('RW %02d', $i);
        }

        return $options;
    }

    /** @return list<string> */
    public function getDaftarRtProperty(): array
    {
        if ($this->detailTipe === null) {
            return [];
        }

        $options = [];
        for ($i = 1; $i <= 30; $i++) {
            $options[] = sprintf('RT %02d', $i);
        }

        return $options;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $keluarga = Keluarga::count();

        // Satu query agregat untuk seluruh angka kartu ringkasan anggota
        // (sebelumnya 6 query COUNT terpisah). Global scope wilayah tetap berlaku.
        $agg = Anggota::query()
            ->selectRaw(
                'COUNT(*) as total,
                SUM(CASE WHEN status_kependudukan = ? OR status_kependudukan IS NULL THEN 1 ELSE 0 END) as menetap,
                SUM(CASE WHEN status_kependudukan = ? THEN 1 ELSE 0 END) as pindah,
                SUM(CASE WHEN id_hubungan = ? THEN 1 ELSE 0 END) as kepala,
                SUM(CASE WHEN id_hubungan = ? AND jenis_kelamin = ? THEN 1 ELSE 0 END) as kepala_perempuan,
                SUM(CASE WHEN tanggal_lahir <= ? AND tanggal_lahir > ? THEN 1 ELSE 0 END) as produktif',
                [
                    'menetap',
                    'pindah',
                    RefHubungan::KEPALA,
                    RefHubungan::KEPALA, 'P',
                    now()->subYears(18)->toDateTimeString(), now()->subYears(60)->toDateTimeString(),
                ]
            )
            ->toBase()
            ->first();

        $anggota = (int) ($agg->total ?? 0);
        $wargaMenetap = (int) ($agg->menetap ?? 0);
        $wargaPindah = (int) ($agg->pindah ?? 0);
        $kepala = (int) ($agg->kepala ?? 0);
        $kepalaPerempuan = (int) ($agg->kepala_perempuan ?? 0);
        $produktif = (int) ($agg->produktif ?? 0);

        $user = auth()->user();
        $wilayahDesc = WilayahScope::label($user);

        return [
            'stats' => [
                [
                    'key' => 'keluarga',
                    'category' => 'Kependudukan',
                    'label' => 'Total Keluarga',
                    'value' => $keluarga,
                    'description' => $wilayahDesc,
                    'icon' => 'heroicon-o-home-modern',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'key' => 'warga_menetap',
                    'category' => 'Kependudukan',
                    'label' => 'Warga Menetap (Aktif)',
                    'value' => $wargaMenetap,
                    'description' => $anggota > 0
                        ? round($wargaMenetap / $anggota * 100).'% dari total '.$anggota.' jiwa'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-users',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'key' => 'warga_pindah',
                    'category' => 'Mutasi Warga',
                    'label' => 'Warga Pindah',
                    'value' => $wargaPindah,
                    'description' => $wargaPindah > 0
                        ? $wargaPindah.' jiwa tercatat pindah domisili'
                        : 'Tidak ada mutasi pindah',
                    'icon' => 'heroicon-o-arrow-right-start-on-rectangle',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'key' => 'kepala_perempuan',
                    'category' => 'Sosial & Usia',
                    'label' => 'Kepala Keluarga Perempuan',
                    'value' => $kepalaPerempuan,
                    'description' => $kepala > 0
                        ? round($kepalaPerempuan / $kepala * 100).'% dari '.$kepala.' kepala keluarga'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-user',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'key' => 'usia_produktif',
                    'category' => 'Sosial & Usia',
                    'label' => 'Usia Produktif (18–59)',
                    'value' => $produktif,
                    'description' => $anggota > 0
                        ? round($produktif / $anggota * 100).'% dari total anggota'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-briefcase',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
            ],
            'detail' => $this->detailTipe !== null ? $this->buildDetailData() : null,
            'opsiPendidikan' => $this->detailTipe !== null ? RefPendidikan::orderBy('id')->pluck('nama', 'id')->all() : [],
            'opsiPekerjaan' => $this->detailTipe !== null ? RefPekerjaan::orderBy('id')->pluck('nama', 'id')->all() : [],
            'opsiHubungan' => $this->detailTipe !== null ? RefHubungan::orderBy('id')->pluck('nama', 'id')->all() : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDetailData(): array
    {
        $tipe = $this->detailTipe ?? 'ringkasan';
        $kunci = $this->detailKunci ?? 'semua';
        $wilayahDesc = WilayahScope::label(auth()->user());

        $subKategori = $this->getSubKategoriOptions($tipe);

        $isModeKeluarga = ($tipe === 'ringkasan' && $kunci === 'keluarga')
            || $tipe === 'keluarga_per_rt';

        if ($isModeKeluarga) {
            return $this->buildDetailKeluarga($tipe, $kunci, $wilayahDesc, $subKategori);
        }

        return $this->buildDetailAnggota($tipe, $kunci, $wilayahDesc, $subKategori);
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    protected function getSubKategoriOptions(string $tipe): array
    {
        return match ($tipe) {
            'ringkasan' => [
                ['key' => 'keluarga', 'label' => 'Total Keluarga', 'count' => Keluarga::count()],
                [
                    'key' => 'warga_menetap',
                    'label' => 'Warga Menetap (Aktif)',
                    'count' => Anggota::where(fn ($q) => $q->where('status_kependudukan', 'menetap')->orWhereNull('status_kependudukan'))->count(),
                ],
                ['key' => 'warga_pindah', 'label' => 'Warga Pindah', 'count' => Anggota::where('status_kependudukan', 'pindah')->count()],
                [
                    'key' => 'kepala_perempuan',
                    'label' => 'Kepala Keluarga Perempuan',
                    'count' => Anggota::where('id_hubungan', RefHubungan::KEPALA)->where('jenis_kelamin', 'P')->count(),
                ],
                [
                    'key' => 'usia_produktif',
                    'label' => 'Usia Produktif (18–59)',
                    'count' => Anggota::where('tanggal_lahir', '<=', now()->subYears(18))->where('tanggal_lahir', '>', now()->subYears(60))->count(),
                ],
            ],
            'jenis_kelamin' => [
                ['key' => 'semua', 'label' => 'Semua Warga', 'count' => Anggota::count()],
                ['key' => 'L', 'label' => 'Laki-laki', 'count' => Anggota::where('jenis_kelamin', 'L')->count()],
                ['key' => 'P', 'label' => 'Perempuan', 'count' => Anggota::where('jenis_kelamin', 'P')->count()],
            ],
            'pendidikan' => array_merge(
                [['key' => 'semua', 'label' => 'Semua Pendidikan', 'count' => Anggota::count()]],
                Anggota::query()
                    ->selectRaw('anggota.id_pendidikan as id_ref, ref_pendidikan.nama as label, COUNT(*) as total')
                    ->join('ref_pendidikan', 'ref_pendidikan.id', '=', 'anggota.id_pendidikan')
                    ->groupBy('anggota.id_pendidikan', 'ref_pendidikan.nama')
                    ->orderBy('anggota.id_pendidikan')
                    ->get()
                    ->map(fn ($r): array => [
                        'key' => (string) $r->id_ref,
                        'label' => (string) $r->label,
                        'count' => (int) $r->total,
                    ])
                    ->all()
            ),
            'pekerjaan' => array_merge(
                [['key' => 'semua', 'label' => 'Semua Pekerjaan', 'count' => Anggota::count()]],
                Anggota::query()
                    ->selectRaw('anggota.id_pekerjaan as id_ref, ref_pekerjaan.nama as label, COUNT(*) as total')
                    ->join('ref_pekerjaan', 'ref_pekerjaan.id', '=', 'anggota.id_pekerjaan')
                    ->groupBy('anggota.id_pekerjaan', 'ref_pekerjaan.nama')
                    ->orderBy('anggota.id_pekerjaan')
                    ->get()
                    ->map(fn ($r): array => [
                        'key' => (string) $r->id_ref,
                        'label' => (string) $r->label,
                        'count' => (int) $r->total,
                    ])
                    ->all()
            ),
            'kelompok_umur' => $this->getKelompokUmurSubKategori(),
            'keluarga_per_rt' => $this->getKeluargaPerRtSubKategori(),
            default => [],
        };
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    protected function getKelompokUmurSubKategori(): array
    {
        $buckets = self::umurBuckets();
        $now = now();
        $items = [
            ['key' => 'semua', 'label' => 'Semua Kelompok Umur', 'count' => Anggota::count()],
        ];

        foreach ($buckets as $key => $info) {
            $q = Anggota::query()->where('tanggal_lahir', '<=', $now->copy()->subYears($info['min']));
            if ($info['max'] !== null) {
                $q->where('tanggal_lahir', '>', $now->copy()->subYears($info['max'] + 1));
            }
            $items[] = [
                'key' => $key,
                'label' => $info['label'],
                'count' => $q->count(),
            ];
        }

        return $items;
    }

    /**
     * @return list<array{key: string, label: string, count: int}>
     */
    protected function getKeluargaPerRtSubKategori(): array
    {
        $rows = Keluarga::query()
            ->selectRaw('keluarga.id_wilayah, COUNT(*) as total')
            ->join('wilayah', 'wilayah.id', '=', 'keluarga.id_wilayah')
            ->groupBy('keluarga.id_wilayah', 'wilayah.path')
            ->orderBy('wilayah.path')
            ->get();

        $wilayahs = Wilayah::query()
            ->whereIn('id', $rows->pluck('id_wilayah'))
            ->with([Wilayah::RELASI_LELUHUR])
            ->get()
            ->keyBy('id');

        $items = [
            ['key' => 'semua', 'label' => 'Semua RT', 'count' => Keluarga::count()],
        ];

        foreach ($rows as $row) {
            /** @var Wilayah|null $w */
            $w = $wilayahs->get($row->id_wilayah);
            $chain = $w
                ? $w->ancestors()
                    ->reject(fn (Wilayah $a): bool => $a->level === 'negara')
                    ->reverse()
                    ->take(2)
                    ->pluck('nama')
                    ->implode(' · ')
                : '';
            $label = $w ? $w->nama.($chain !== '' ? ' · '.$chain : '') : 'Wilayah #'.$row->id_wilayah;

            $items[] = [
                'key' => (string) $row->id_wilayah,
                'label' => $label,
                'count' => (int) $row->total,
            ];
        }

        return $items;
    }

    /**
     * @return array<string, array{label: string, min: int, max: ?int}>
     */
    public static function umurBuckets(): array
    {
        return [
            'balita' => ['label' => 'Balita (0–5)', 'min' => 0, 'max' => 5],
            'anak' => ['label' => 'Anak (6–12)', 'min' => 6, 'max' => 12],
            'remaja' => ['label' => 'Remaja (13–17)', 'min' => 13, 'max' => 17],
            'dewasa' => ['label' => 'Dewasa (18–59)', 'min' => 18, 'max' => 59],
            'lansia' => ['label' => 'Lansia (60+)', 'min' => 60, 'max' => null],
        ];
    }

    protected function applyDemographicFiltersToAnggotaQuery(Builder $query): void
    {
        if ($this->filterJk !== '') {
            $query->where('jenis_kelamin', $this->filterJk);
        }

        if ($this->filterUmurKategori !== '') {
            $buckets = self::umurBuckets();
            if (isset($buckets[$this->filterUmurKategori])) {
                $info = $buckets[$this->filterUmurKategori];
                $now = now();
                $query->where('tanggal_lahir', '<=', $now->copy()->subYears($info['min']));
                if ($info['max'] !== null) {
                    $query->where('tanggal_lahir', '>', $now->copy()->subYears($info['max'] + 1));
                }
            }
        }

        if (filled($this->filterUmurMin) && is_numeric($this->filterUmurMin)) {
            $min = max(0, (int) $this->filterUmurMin);
            $query->where('tanggal_lahir', '<=', now()->subYears($min));
        }

        if (filled($this->filterUmurMax) && is_numeric($this->filterUmurMax)) {
            $max = max(0, (int) $this->filterUmurMax);
            $query->where('tanggal_lahir', '>', now()->subYears($max + 1));
        }

        if ($this->filterPendidikan !== '' && is_numeric($this->filterPendidikan)) {
            $query->where('id_pendidikan', (int) $this->filterPendidikan);
        }

        if ($this->filterPekerjaan !== '' && is_numeric($this->filterPekerjaan)) {
            $query->where('id_pekerjaan', (int) $this->filterPekerjaan);
        }

        if ($this->filterHubungan !== '' && is_numeric($this->filterHubungan)) {
            $query->where('id_hubungan', (int) $this->filterHubungan);
        }

        if ($this->filterStatusWarga !== '') {
            if ($this->filterStatusWarga === 'menetap') {
                $query->where(fn ($q) => $q->where('status_kependudukan', 'menetap')->orWhereNull('status_kependudukan'));
            } else {
                $query->where('status_kependudukan', $this->filterStatusWarga);
            }
        }
    }

    protected function hasDemographicFilter(): bool
    {
        return $this->filterJk !== ''
            || $this->filterUmurKategori !== ''
            || filled($this->filterUmurMin)
            || filled($this->filterUmurMax)
            || $this->filterPendidikan !== ''
            || $this->filterPekerjaan !== ''
            || $this->filterHubungan !== ''
            || $this->filterStatusWarga !== '';
    }

    protected function matchesWilayahFilter(?Wilayah $rt): bool
    {
        if (! $this->hasFilterWilayah()) {
            return true;
        }

        if ($rt === null) {
            return false;
        }

        $ancestors = $rt->ancestors()->keyBy('level');

        if (filled($this->filterProvinsi)) {
            $nama = mb_strtolower((string) ($ancestors->get('provinsi')?->nama ?? ''));
            if (! str_contains($nama, mb_strtolower(trim((string) $this->filterProvinsi)))) {
                return false;
            }
        }

        if (filled($this->filterKabkota)) {
            $nama = mb_strtolower((string) ($ancestors->get('kabupaten_kota')?->nama ?? ''));
            if (! str_contains($nama, mb_strtolower(trim((string) $this->filterKabkota)))) {
                return false;
            }
        }

        if (filled($this->filterKecamatan)) {
            $nama = mb_strtolower((string) ($ancestors->get('kecamatan')?->nama ?? ''));
            if (! str_contains($nama, mb_strtolower(trim((string) $this->filterKecamatan)))) {
                return false;
            }
        }

        if (filled($this->filterDesa)) {
            $term = mb_strtolower(trim((string) $this->filterDesa));
            $desaNama = mb_strtolower((string) ($ancestors->get('desa')?->nama ?? ''));
            $dusunNama = mb_strtolower((string) ($ancestors->get('dusun')?->nama ?? ''));
            if (! str_contains($desaNama, $term) && ! str_contains($dusunNama, $term)) {
                return false;
            }
        }

        if (filled($this->filterRw)) {
            $rwNama = (string) ($ancestors->get('rw')?->nama ?? '');
            if (! $this->cocokNomorAtauNamaWilayah($rwNama, (string) $this->filterRw)) {
                return false;
            }
        }

        if (filled($this->filterRt)) {
            if (! $this->cocokNomorAtauNamaWilayah((string) $rt->nama, (string) $this->filterRt)) {
                return false;
            }
        }

        return true;
    }

    protected function cocokNomorAtauNamaWilayah(string $namaAktual, string $input): bool
    {
        $aktualLower = mb_strtolower(trim($namaAktual));
        $inputLower = mb_strtolower(trim($input));

        if ($inputLower === '') {
            return true;
        }

        if (str_contains($aktualLower, $inputLower)) {
            return true;
        }

        $digitsInput = ltrim((string) preg_replace('/\D/', '', $inputLower), '0');
        $digitsAktual = ltrim((string) preg_replace('/\D/', '', $aktualLower), '0');

        return $digitsInput !== '' && $digitsInput === $digitsAktual;
    }

    /**
     * @param  list<array{key: string, label: string, count: int}>  $subKategori
     * @return array<string, mixed>
     */
    protected function buildDetailKeluarga(string $tipe, string $kunci, string $wilayahDesc, array $subKategori): array
    {
        $query = Keluarga::query()
            ->with([
                'kepala',
                'anggota' => fn ($q) => $q->orderBy('id_hubungan')->orderBy('tanggal_lahir'),
                'anggota.hubungan',
                'anggota.pendidikan',
                'anggota.pekerjaan',
                'wilayah.'.Wilayah::RELASI_LELUHUR,
            ])
            ->orderBy('id_wilayah')
            ->orderBy('id');

        $judul = 'Daftar Total Keluarga';

        if ($tipe === 'keluarga_per_rt') {
            if ($kunci !== 'semua' && is_numeric($kunci)) {
                $query->where('id_wilayah', (int) $kunci);
                $rt = Wilayah::with([Wilayah::RELASI_LELUHUR])->find((int) $kunci);
                $judul = 'Keluarga di '.($rt ? $rt->alamatLengkap() : 'RT #'.$kunci);
            } else {
                $judul = 'Sebaran Keluarga per RT (Semua RT)';
            }
        }

        if ($this->hasDemographicFilter()) {
            $query->whereHas('anggota', function (Builder $aq): void {
                $this->applyDemographicFiltersToAnggotaQuery($aq);
            });
        }

        $totalSemua = (clone $query)->count();
        $records = $query->limit(200)->get();

        $cari = mb_strtolower(trim($this->cariDetail));

        $records = $records->filter(function (Keluarga $k) use ($cari): bool {
            if (! $this->matchesWilayahFilter($k->wilayah)) {
                return false;
            }

            if ($cari === '') {
                return true;
            }

            $kepalaNama = mb_strtolower((string) ($k->kepala?->nama_lengkap ?? ''));
            $wilayahNama = mb_strtolower((string) ($k->wilayah?->alamatLengkap() ?? ''));
            $alamat = mb_strtolower((string) ($k->alamat ?? ''));
            $noKkMasked = mb_strtolower((string) ($k->no_kk_masked ?? ''));

            if (str_contains($kepalaNama, $cari)
                || str_contains($wilayahNama, $cari)
                || str_contains($alamat, $cari)
                || str_contains($noKkMasked, $cari)
            ) {
                return true;
            }

            foreach ($k->anggota as $a) {
                if (str_contains(mb_strtolower((string) $a->nama_lengkap), $cari)) {
                    return true;
                }
            }

            return false;
        })->values();

        $items = $records->map(function (Keluarga $k): array {
            $infoWilayah = $this->formatWilayahRingkas($k->wilayah);

            return [
                'id' => $k->id,
                'kepala_nama' => $k->kepala?->nama_lengkap ?? 'Belum ada Kepala Keluarga',
                'no_kk_masked' => $k->no_kk_masked ?? '—',
                'wilayah' => $k->wilayah?->alamatLengkap() ?? '—',
                'wilayah_singkat' => $infoWilayah['rt_rw'],
                'wilayah_daerah' => $infoWilayah['daerah'],
                'alamat' => $k->alamat ?? '—',
                'kode_pos' => $k->kode_pos ?? '—',
                'jumlah_anggota' => $k->anggota->count(),
                'url_kk' => KeluargaResource::getUrl('view', ['record' => $k->id]),
                'url_cetak_kk' => route('keluarga.cetak', $k->id),
                'anggota' => $k->anggota->map(fn (Anggota $a): array => [
                    'id' => $a->id,
                    'nama' => $a->nama_lengkap ?? '—',
                    'nik_masked' => $a->nik_masked ?? '—',
                    'hubungan' => $a->hubungan?->nama ?? '—',
                    'is_kepala' => (int) $a->id_hubungan === RefHubungan::KEPALA,
                    'jenis_kelamin' => $a->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
                    'jk_kode' => $a->jenis_kelamin,
                    'umur' => $a->tanggal_lahir ? (int) $a->tanggal_lahir->diffInYears(now()) : null,
                    'pendidikan' => $a->pendidikan?->nama ?? '—',
                    'pekerjaan' => $a->pekerjaan?->nama ?? '—',
                    'status' => $a->status_kependudukan ?? 'menetap',
                    'url_anggota' => AnggotaResource::getUrl('view', ['record' => $a->id]),
                ])->all(),
            ];
        })->all();

        $totalAnggotaTampil = array_sum(array_column($items, 'jumlah_anggota'));

        return [
            'mode' => 'keluarga',
            'tipe' => $tipe,
            'kunci' => $kunci,
            'judul' => $judul,
            'deskripsi' => $wilayahDesc,
            'total_item' => count($items),
            'total_semua' => $totalSemua,
            'total_keluarga' => count($items),
            'total_anggota' => $totalAnggotaTampil,
            'sub_kategori' => $subKategori,
            'items' => $items,
        ];
    }

    /**
     * @param  list<array{key: string, label: string, count: int}>  $subKategori
     * @return array<string, mixed>
     */
    protected function buildDetailAnggota(string $tipe, string $kunci, string $wilayahDesc, array $subKategori): array
    {
        $query = Anggota::query()
            ->with([
                'hubungan',
                'pendidikan',
                'pekerjaan',
                'keluarga.kepala',
                'keluarga.anggota' => fn ($q) => $q->orderBy('id_hubungan')->orderBy('tanggal_lahir'),
                'keluarga.anggota.hubungan',
                'keluarga.wilayah.'.Wilayah::RELASI_LELUHUR,
            ])
            ->orderBy('id_keluarga')
            ->orderBy('id_hubungan')
            ->orderBy('id');

        $judul = $this->applyAnggotaCategoryFilter($query, $tipe, $kunci);
        $this->applyDemographicFiltersToAnggotaQuery($query);

        $totalSemua = (clone $query)->count();
        $records = $query->limit(250)->get();

        $cari = mb_strtolower(trim($this->cariDetail));

        $records = $records->filter(function (Anggota $a) use ($cari): bool {
            if (! $this->matchesWilayahFilter($a->keluarga?->wilayah)) {
                return false;
            }

            if ($cari === '') {
                return true;
            }

            $nama = mb_strtolower((string) ($a->nama_lengkap ?? ''));
            $kepala = mb_strtolower((string) ($a->keluarga?->kepala?->nama_lengkap ?? ''));
            $wilayah = mb_strtolower((string) ($a->keluarga?->wilayah?->alamatLengkap() ?? ''));
            $pendidikan = mb_strtolower((string) ($a->pendidikan?->nama ?? ''));
            $pekerjaan = mb_strtolower((string) ($a->pekerjaan?->nama ?? ''));
            $hubungan = mb_strtolower((string) ($a->hubungan?->nama ?? ''));

            return str_contains($nama, $cari)
                || str_contains($kepala, $cari)
                || str_contains($wilayah, $cari)
                || str_contains($pendidikan, $cari)
                || str_contains($pekerjaan, $cari)
                || str_contains($hubungan, $cari);
        })->values();

        $keluargaIds = [];

        $items = $records->map(function (Anggota $a) use (&$keluargaIds): array {
            if ($a->id_keluarga) {
                $keluargaIds[$a->id_keluarga] = true;
            }

            $keluarga = $a->keluarga;
            $infoWilayah = $this->formatWilayahRingkas($keluarga?->wilayah);
            $anggotaSekeluarga = $keluarga?->anggota?->map(fn (Anggota $m): array => [
                'id' => $m->id,
                'nama' => $m->nama_lengkap ?? '—',
                'hubungan' => $m->hubungan?->nama ?? '—',
                'is_kepala' => (int) $m->id_hubungan === RefHubungan::KEPALA,
                'is_current' => $m->id === $a->id,
                'url_anggota' => AnggotaResource::getUrl('view', ['record' => $m->id]),
            ])->all() ?? [];

            return [
                'id' => $a->id,
                'nama' => $a->nama_lengkap ?? '—',
                'nik_masked' => $a->nik_masked ?? '—',
                'hubungan' => $a->hubungan?->nama ?? '—',
                'is_kepala' => (int) $a->id_hubungan === RefHubungan::KEPALA,
                'jenis_kelamin' => $a->jenis_kelamin === 'L' ? 'Laki-laki' : 'Perempuan',
                'jk_kode' => $a->jenis_kelamin,
                'umur' => $a->tanggal_lahir ? (int) $a->tanggal_lahir->diffInYears(now()) : null,
                'tanggal_lahir' => $a->tanggal_lahir?->translatedFormat('d M Y') ?? '—',
                'pendidikan' => $a->pendidikan?->nama ?? '—',
                'pekerjaan' => $a->pekerjaan?->nama ?? '—',
                'status' => $a->status_kependudukan ?? 'menetap',
                'id_keluarga' => $a->id_keluarga,
                'kepala_keluarga' => $keluarga?->kepala?->nama_lengkap ?? 'Belum ada Kepala Keluarga',
                'no_kk_masked' => $keluarga?->no_kk_masked ?? '—',
                'wilayah' => $keluarga?->wilayah?->alamatLengkap() ?? '—',
                'wilayah_singkat' => $infoWilayah['rt_rw'],
                'wilayah_daerah' => $infoWilayah['daerah'],
                'alamat' => $keluarga?->alamat ?? '—',
                'total_sekeluarga' => count($anggotaSekeluarga),
                'anggota_sekeluarga' => $anggotaSekeluarga,
                'url_kk' => $a->id_keluarga ? KeluargaResource::getUrl('view', ['record' => $a->id_keluarga]) : '#',
                'url_cetak_kk' => $a->id_keluarga ? route('keluarga.cetak', $a->id_keluarga) : '#',
                'url_anggota' => AnggotaResource::getUrl('view', ['record' => $a->id]),
            ];
        })->all();

        return [
            'mode' => 'anggota',
            'tipe' => $tipe,
            'kunci' => $kunci,
            'judul' => $judul,
            'deskripsi' => $wilayahDesc,
            'total_item' => count($items),
            'total_semua' => $totalSemua,
            'total_keluarga' => count($keluargaIds),
            'total_anggota' => count($items),
            'sub_kategori' => $subKategori,
            'items' => $items,
        ];
    }

    protected function applyAnggotaCategoryFilter(Builder $query, string $tipe, string $kunci): string
    {
        if ($tipe === 'ringkasan') {
            return match ($kunci) {
                'warga_menetap' => (function () use ($query): string {
                    $query->where(fn ($q) => $q->where('status_kependudukan', 'menetap')->orWhereNull('status_kependudukan'));

                    return 'Warga Menetap (Aktif)';
                })(),
                'warga_pindah' => (function () use ($query): string {
                    $query->where('status_kependudukan', 'pindah');

                    return 'Warga Pindah Domisili';
                })(),
                'kepala_perempuan' => (function () use ($query): string {
                    $query->where('id_hubungan', RefHubungan::KEPALA)->where('jenis_kelamin', 'P');

                    return 'Kepala Keluarga Perempuan';
                })(),
                'usia_produktif' => (function () use ($query): string {
                    $query->where('tanggal_lahir', '<=', now()->subYears(18))
                        ->where('tanggal_lahir', '>', now()->subYears(60));

                    return 'Warga Usia Produktif (18–59 Tahun)';
                })(),
                default => 'Daftar Warga',
            };
        }

        if ($tipe === 'jenis_kelamin') {
            if ($kunci === 'L') {
                $query->where('jenis_kelamin', 'L');

                return 'Komposisi Jenis Kelamin · Laki-laki';
            }
            if ($kunci === 'P') {
                $query->where('jenis_kelamin', 'P');

                return 'Komposisi Jenis Kelamin · Perempuan';
            }

            return 'Komposisi Jenis Kelamin · Semua Warga';
        }

        if ($tipe === 'pendidikan') {
            if ($kunci !== 'semua' && is_numeric($kunci)) {
                $query->where('id_pendidikan', (int) $kunci);
                $nama = RefPendidikan::whereKey((int) $kunci)->value('nama') ?? 'Pendidikan #'.$kunci;

                return 'Sebaran Pendidikan · '.$nama;
            }

            return 'Sebaran Pendidikan · Semua Jenjang';
        }

        if ($tipe === 'pekerjaan') {
            if ($kunci !== 'semua' && is_numeric($kunci)) {
                $query->where('id_pekerjaan', (int) $kunci);
                $nama = RefPekerjaan::whereKey((int) $kunci)->value('nama') ?? 'Pekerjaan #'.$kunci;

                return 'Sebaran Pekerjaan · '.$nama;
            }

            return 'Sebaran Pekerjaan · Semua Pekerjaan';
        }

        if ($tipe === 'kelompok_umur') {
            $buckets = self::umurBuckets();
            if ($kunci !== 'semua' && isset($buckets[$kunci])) {
                $info = $buckets[$kunci];
                $now = now();
                $query->where('tanggal_lahir', '<=', $now->copy()->subYears($info['min']));
                if ($info['max'] !== null) {
                    $query->where('tanggal_lahir', '>', $now->copy()->subYears($info['max'] + 1));
                }

                return 'Sebaran Kelompok Umur · '.$info['label'];
            }

            return 'Sebaran Kelompok Umur · Semua Usia';
        }

        return 'Rincian Data Warga';
    }

    /**
     * @return array{rt_rw: string, daerah: string}
     */
    protected function formatWilayahRingkas(?Wilayah $rt): array
    {
        if ($rt === null) {
            return ['rt_rw' => '—', 'daerah' => ''];
        }

        $ancestors = $rt->ancestors()->keyBy('level');
        $rw = $ancestors->get('rw');
        $dusun = $ancestors->get('dusun');
        $desa = $ancestors->get('desa');
        $kab = $ancestors->get('kabupaten_kota');

        $rtRw = $rt->nama.($rw !== null ? ' · '.$rw->nama : '');

        $bagianDaerah = array_values(array_filter([
            $dusun?->nama,
            $desa?->nama,
            $kab?->nama,
        ]));

        return [
            'rt_rw' => $rtRw,
            'daerah' => implode(', ', $bagianDaerah),
        ];
    }

    public function render(): View
    {
        return view($this->view, $this->getViewData());
    }
}
