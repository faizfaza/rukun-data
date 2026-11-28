<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class NotifikasiValidasi extends Model
{
    public const JENIS_DISETUJUI_RW = 'disetujui_rw';

    public const JENIS_DISETUJUI_DESA = 'disetujui_desa';

    public const JENIS_DITOLAK_RW = 'ditolak_rw';

    public const JENIS_DITOLAK_DESA = 'ditolak_desa';

    public const JENIS_SIAP_DISAHKAN_DESA = 'siap_disahkan_desa';

    protected $table = 'notifikasi_validasi';

    protected $fillable = [
        'id_pengguna',
        'id_validasi',
        'jenis',
        'judul',
        'pesan',
        'tautan',
        'dibaca_pada',
    ];

    protected function casts(): array
    {
        return [
            'dibaca_pada' => 'datetime',
        ];
    }

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }

    public function validasi(): BelongsTo
    {
        return $this->belongsTo(ValidasiPerubahan::class, 'id_validasi');
    }

    public function scopeBelumDibaca(Builder $query): Builder
    {
        return $query->whereNull('dibaca_pada');
    }

    public function sudahDibaca(): bool
    {
        return $this->dibaca_pada !== null;
    }

    public function tandaiDibaca(): void
    {
        if ($this->dibaca_pada === null) {
            $this->update(['dibaca_pada' => now()]);
        }
    }

    public static function kirim(
        int $idPengguna,
        ?int $idValidasi,
        string $jenis,
        string $judul,
        string $pesan,
        ?string $tautan = null,
    ): ?self {
        try {
            if (! Schema::hasTable('notifikasi_validasi')) {
                return null;
            }

            return static::create([
                'id_pengguna' => $idPengguna,
                'id_validasi' => $idValidasi,
                'jenis' => $jenis,
                'judul' => $judul,
                'pesan' => $pesan,
                'tautan' => $tautan,
            ]);
        } catch (\Throwable) {
            return null;
        }
    }
}
