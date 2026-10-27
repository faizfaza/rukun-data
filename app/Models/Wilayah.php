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

    protected $fillable = ['kode', 'nama', 'level', 'tipe', 'parent_id'];

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
