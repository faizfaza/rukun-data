<?php

namespace App\Models;

use App\Support\SensitiveData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $table = 'audit_log';

    public $timestamps = false;

    protected $fillable = ['id_pengguna', 'aksi', 'nama_tabel', 'id_record', 'detail', 'waktu'];

    protected $hidden = ['detail'];

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }

    public function setDetailAttribute(mixed $value): void
    {
        if ($value === null || $value === '' || $value === []) {
            $this->attributes['detail'] = null;

            return;
        }

        $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
        $this->attributes['detail'] = $json ? SensitiveData::encrypt($json) : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetailDataAttribute(): array
    {
        $raw = $this->attributes['detail'] ?? null;
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

    public function pengguna(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengguna');
    }
}
