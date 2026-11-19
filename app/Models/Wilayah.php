<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

// Keputusan 1: hierarki wilayah nasional. Relasi leluhur/keturunan memakai
// kolom `path` (LIKE prefix), bukan rekursi, agar tetap murah di skala besar.
class Wilayah extends Model
{
    protected $table = 'wilayah';

    // Rantai induk terdalam (RT -> RW -> dusun -> desa -> kecamatan ->
    // kabupaten/kota -> provinsi -> negara) untuk eager loading.
    public const RELASI_LELUHUR = 'parent.parent.parent.parent.parent.parent.parent';

    protected $attributes = ['is_active' => true, 'sumber' => 'lokal'];

    protected $fillable = ['kode', 'nama', 'level', 'tipe', 'parent_id', 'is_active', 'sumber', 'kode_sumber', 'versi_sumber', 'pengaturan'];

    /**
     * Pilihan palet warna tema kustomisasi per Desa.
     *
     * @var array<string, array{label: string, desc: string, hex: string, shades: array<int, string>}>
     */
    public const PALET_WARNA_DESA = [
        'teal' => [
            'label' => 'Civic Teal (Bawaan)',
            'desc' => 'Hijau kebiruan tenang & modern khas Rukun Data',
            'hex' => '#0f766e',
            'shades' => [
                50 => '#f0fdfa', 100 => '#ccfbf1', 200 => '#99f6e4', 300 => '#5eead4', 400 => '#2dd4bf',
                500 => '#14b8a6', 600 => '#0f766e', 700 => '#115e59', 800 => '#134e4a', 900 => '#0f2e2b', 950 => '#08201e',
            ],
        ],
        'emerald' => [
            'label' => 'Hijau Zamrud Desa (Emerald)',
            'desc' => 'Hijau asri bernuansa agraris & kemakmuran desa',
            'hex' => '#047857',
            'shades' => [
                50 => '#ecfdf5', 100 => '#d1fae5', 200 => '#a7f3d0', 300 => '#6ee7b7', 400 => '#34d399',
                500 => '#10b981', 600 => '#059669', 700 => '#047857', 800 => '#065f46', 900 => '#064e3b', 950 => '#022c22',
            ],
        ],
        'blue' => [
            'label' => 'Biru Pemerintahan (Royal Blue)',
            'desc' => 'Biru formal instansi pelayanan publik & administrasi',
            'hex' => '#1d4ed8',
            'shades' => [
                50 => '#eff6ff', 100 => '#dbeafe', 200 => '#bfdbfe', 300 => '#93c5fd', 400 => '#60a5fa',
                500 => '#3b82f6', 600 => '#2563eb', 700 => '#1d4ed8', 800 => '#1e40af', 900 => '#1e3a8a', 950 => '#172554',
            ],
        ],
        'indigo' => [
            'label' => 'Biru Nusantara (Indigo)',
            'desc' => 'Biru keunguan tegas, berwibawa, & kontras tinggi',
            'hex' => '#4338ca',
            'shades' => [
                50 => '#eef2ff', 100 => '#e0e7ff', 200 => '#c7d2fe', 300 => '#a5b4fc', 400 => '#818cf8',
                500 => '#6366f1', 600 => '#4f46e5', 700 => '#4338ca', 800 => '#3730a3', 900 => '#312e81', 950 => '#1e1b4b',
            ],
        ],
        'amber' => [
            'label' => 'Kuning Keemasan (Amber Klasik)',
            'desc' => 'Nuansa hangat budaya & kearifan lokal desa',
            'hex' => '#b45309',
            'shades' => [
                50 => '#fffbeb', 100 => '#fef3c7', 200 => '#fde68a', 300 => '#fcd34d', 400 => '#fbbf24',
                500 => '#f59e0b', 600 => '#d97706', 700 => '#b45309', 800 => '#92400e', 900 => '#78350f', 950 => '#451a03',
            ],
        ],
        'rose' => [
            'label' => 'Merah Marun (Crimson)',
            'desc' => 'Merah marun elegan & berani',
            'hex' => '#be123c',
            'shades' => [
                50 => '#fff1f2', 100 => '#ffe4e6', 200 => '#fecdd3', 300 => '#fda4af', 400 => '#fb7185',
                500 => '#f43f5e', 600 => '#e11d48', 700 => '#be123c', 800 => '#9f1239', 900 => '#881337', 950 => '#4c0519',
            ],
        ],
    ];

    /**
     * Nilai default pengaturan kustomisasi & operasional per Desa.
     *
     * @var array<string, mixed>
     */
    public const DEFAULT_PENGATURAN_DESA = [
        'nama_tampilan' => '',
        'slogan' => 'Sistem Informasi Kependudukan Desa',
        'warna_tema' => 'teal',
        'nama_kepala_desa' => '',
        'nip_kepala_desa' => '',
        'alamat_kantor' => '',
        'telepon_desa' => '',
        'wajib_validasi_berjenjang' => true,
        'rw_lihat_penuh' => false,
        'arsip_otomatis_aktif' => true,
        'interval_arsip_jam' => 36,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'pengaturan' => 'array',
        ];
    }

    public function aktifEfektif(): bool
    {
        return $this->is_active && ! self::whereIn('id', $this->ancestorIds())->where('is_active', false)->exists();
    }

    public function scopeOperasional(Builder $query): Builder
    {
        return $query->where('is_active', true)->whereNotExists(function ($sub) {
            $sub->selectRaw('1')->from('wilayah as induk_nonaktif')
                ->where('induk_nonaktif.is_active', false)
                ->whereRaw('wilayah.path LIKE '.(DB::getDriverName() === 'sqlite'
                    ? "induk_nonaktif.path || '%'" : "CONCAT(induk_nonaktif.path, '%')"));
        });
    }

    // Peta induk yang valid per level; null = akar (negara). Array berarti
    // salah satu level tersebut boleh menjadi induk (dusun opsional untuk rw).
    /** @var array<string, string|list<string>|null> */
    public const LEVEL_INDUK = [
        'negara' => null,
        'provinsi' => 'negara',
        'kabupaten_kota' => 'provinsi',
        'kecamatan' => 'kabupaten_kota',
        'desa' => 'kecamatan',
        'dusun' => 'desa',
        'rw' => ['dusun', 'desa'],
        'rt' => 'rw',
    ];

    /** @var array<string, string> */
    public const LABEL_LEVEL = [
        'negara' => 'Negara',
        'provinsi' => 'Provinsi',
        'kabupaten_kota' => 'Kabupaten/Kota',
        'kecamatan' => 'Kecamatan',
        'desa' => 'Desa/Kelurahan',
        'dusun' => 'Dusun',
        'rw' => 'RW',
        'rt' => 'RT',
    ];

    /** @return list<string> */
    public static function levels(): array
    {
        return array_keys(self::LEVEL_INDUK);
    }

    public static function labelLevel(string $level): string
    {
        return self::LABEL_LEVEL[$level] ?? $level;
    }

    /** @return array<string, string> */
    public static function levelOptions(): array
    {
        return collect(self::levels())
            ->mapWithKeys(fn (string $level): array => [$level => self::labelLevel($level)])
            ->all();
    }

    // Level induk yang boleh untuk $level (kosong untuk negara).
    /** @return list<string> */
    public static function indukValid(string $level): array
    {
        $induk = self::LEVEL_INDUK[$level] ?? null;

        return match (true) {
            $induk === null => [],
            is_array($induk) => $induk,
            default => [$induk],
        };
    }

    protected static function booted(): void
    {
        // Path diisi otomatis setelah id ada: path induk + id + "/".
        static::created(function (Wilayah $wilayah): void {
            if (filled($wilayah->path)) {
                return;
            }

            DB::transaction(function () use ($wilayah): void {
                $indukPath = $wilayah->parent_id === null
                    ? '/'
                    : (string) self::whereKey($wilayah->parent_id)->value('path');

                $wilayah->forceFill(['path' => $indukPath.$wilayah->id.'/'])->saveQuietly();
            });
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Wilayah::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Wilayah::class, 'parent_id');
    }

    public function keluarga(): HasMany
    {
        return $this->hasMany(Keluarga::class, 'id_wilayah');
    }

    // Leluhur dari akar sampai induk langsung (tidak termasuk diri sendiri).
    /** @return Collection<int, Wilayah> */
    public function ancestors(): Collection
    {
        // Pakai rantai relasi yang sudah di-eager-load bila lengkap, agar
        // daftar tabel tidak menembak satu query per baris.
        if ($this->relationLoaded('parent')) {
            $rantai = [];
            $node = $this->parent;

            while ($node !== null) {
                $rantai[] = $node;

                if ($node->parent_id === null) {
                    break;
                }

                if (! $node->relationLoaded('parent')) {
                    $rantai = null;
                    break;
                }

                $node = $node->parent;
            }

            if ($rantai !== null) {
                return new Collection(array_reverse($rantai));
            }
        }

        $ids = $this->ancestorIds();

        if ($ids === []) {
            return new Collection;
        }

        return self::whereIn('id', $ids)->orderBy('path')->get();
    }

    /** @return list<int> */
    public function ancestorIds(): array
    {
        $segments = array_values(array_filter(
            explode('/', (string) $this->path),
            fn (string $s): bool => $s !== '',
        ));

        // Segmen terakhir adalah diri sendiri; sisanya leluhur.
        array_pop($segments);

        return array_map('intval', $segments);
    }

    // Keturunan (bukan diri sendiri) lewat prefix path.
    /** @return Builder<Wilayah> */
    public function descendants(): Builder
    {
        return self::query()->where('path', 'like', $this->path.'%')->whereKeyNot($this->getKey());
    }

    // Rantai induk terformat rapi dengan koma (spesifik ke umum), tanpa simbol panah '>' yang membingungkan.
    public function rantaiIndukNama(): string
    {
        $ancestors = $this->ancestors()
            ->reject(fn (Wilayah $w): bool => $w->level === 'negara')
            ->pluck('nama')
            ->reverse()
            ->all();

        return implode(', ', $ancestors);
    }

    // Alamat lengkap berbasis koma (misal: "RT 01, RW 01, Dusun Krajan, Desa Bojongsoang, Kecamatan Bojongsoang, Kabupaten Bandung, Jawa Barat").
    public function alamatLengkap(): string
    {
        $rantai = $this->rantaiIndukNama();

        return $rantai !== '' ? "{$this->nama}, {$rantai}" : $this->nama;
    }

    public function desaInduk(): ?self
    {
        if ($this->level === 'desa') {
            return $this;
        }

        /** @var self|null $desa */
        $desa = $this->ancestors()->firstWhere('level', 'desa');

        return $desa;
    }

    public function rwInduk(): ?self
    {
        if ($this->level === 'rw') {
            return $this;
        }

        /** @var self|null $rw */
        $rw = $this->ancestors()->firstWhere('level', 'rw');

        return $rw;
    }

    public function pengaturanDesa(?string $key = null, mixed $default = null): mixed
    {
        $desa = $this->desaInduk();
        $raw = is_array($desa?->pengaturan) ? $desa->pengaturan : [];
        $merged = array_merge(self::DEFAULT_PENGATURAN_DESA, $raw);

        if ($key === null) {
            return $merged;
        }

        return array_key_exists($key, $merged) ? $merged[$key] : $default;
    }

    /**
     * Mengambil Desa Klien utama (desa yang memiliki RW/Dusun/Keluarga aktif atau sudah dikonfigurasi),
     * agar tidak memindai 83.000+ desa nasional secara berurutan abjad.
     */
    public static function desaKlienDefault(): ?self
    {
        // 1. Cari dari RT atau RW yang ada di database (jumlahnya sangat sedikit)
        $subNode = self::withoutGlobalScopes()
            ->whereIn('level', ['rt', 'rw', 'dusun'])
            ->orderBy('id')
            ->first();

        if ($subNode) {
            $desa = $subNode->desaInduk();
            if ($desa) {
                return $desa;
            }
        }

        // 2. Cari desa yang sudah punya pengaturan kustom
        $configured = self::withoutGlobalScopes()
            ->where('level', 'desa')
            ->whereNotNull('pengaturan')
            ->first();

        if ($configured) {
            return $configured;
        }

        // 3. Fallback ke desa pertama berdasarkan ID
        return self::withoutGlobalScopes()
            ->where('level', 'desa')
            ->orderBy('id')
            ->first();
    }

    /**
     * Daftar ID desa yang aktif digunakan (memiliki RW/Dusun/RT atau sudah memiliki pengaturan).
     *
     * @return list<int>
     */
    public static function idDesaKlienAktif(): array
    {
        $paths = self::withoutGlobalScopes()
            ->whereIn('level', ['dusun', 'rw', 'rt'])
            ->pluck('path')
            ->all();

        $candidateIds = [];
        foreach ($paths as $path) {
            foreach (explode('/', (string) $path) as $seg) {
                if ($seg !== '' && ctype_digit($seg)) {
                    $candidateIds[(int) $seg] = true;
                }
            }
        }

        $configuredIds = self::withoutGlobalScopes()
            ->where('level', 'desa')
            ->whereNotNull('pengaturan')
            ->pluck('id')
            ->all();

        foreach ($configuredIds as $cid) {
            $candidateIds[(int) $cid] = true;
        }

        if ($candidateIds === []) {
            $firstId = self::withoutGlobalScopes()->where('level', 'desa')->orderBy('id')->value('id');

            return $firstId ? [(int) $firstId] : [];
        }

        return self::withoutGlobalScopes()
            ->where('level', 'desa')
            ->whereIn('id', array_keys($candidateIds))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    // Mengembalikan pesan kesalahan bila kombinasi level + induk tidak sah,
    // atau null bila sah. Dipakai observer dan rule form Filament.
    public static function pesanIndukTidakValid(string $level, ?int $parentId): ?string
    {
        if (! array_key_exists($level, self::LEVEL_INDUK)) {
            return "Level wilayah \"{$level}\" tidak dikenal.";
        }

        $indukBoleh = self::indukValid($level);

        if ($level === 'negara') {
            return $parentId === null ? null : 'Negara adalah akar dan tidak boleh punya induk.';
        }

        if ($parentId === null) {
            return 'Wilayah selain negara wajib punya induk.';
        }

        $induk = self::find($parentId);

        if ($induk === null) {
            return 'Induk wilayah tidak ditemukan.';
        }

        if (! in_array($induk->level, $indukBoleh, true)) {
            $boleh = implode(' atau ', $indukBoleh);

            return "Wilayah berlevel \"{$level}\" hanya boleh berinduk {$boleh}.";
        }

        return null;
    }
}
