<?php

namespace App\Support;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class OperationalGuard
{
    public static function wilayah(?int $id, ?string $level = null): Wilayah
    {
        $w = Wilayah::find($id);
        if (! $w || ! $w->aktifEfektif() || ($level && $w->level !== $level)) {
            throw ValidationException::withMessages(['id_wilayah' => 'Pilih wilayah aktif dengan level yang sesuai; seluruh induknya harus aktif.']);
        }
        $allowed = WilayahAccess::allowedWilayahIds(auth()->user());
        if ($allowed !== null && ! in_array($w->id, $allowed, true)) {
            throw ValidationException::withMessages(['id_wilayah' => 'Wilayah di luar penugasan Anda.']);
        }

        return $w;
    }

    public function saving($model): void
    {
        if ($model instanceof User && $model->isDirty('identitas')) {
            Validator::make(['identitas' => $model->identitas], [
                'identitas' => 'nullable|array', 'identitas.nik' => 'nullable|regex:/^[0-9]{16}$/',
                'identitas.tanggal_lahir' => 'nullable|date|before_or_equal:today',
                'identitas.*' => 'nullable|string|max:500',
            ])->validate();
        }
        if ($model instanceof Anggota && $model->isDirty('profil')) {
            Validator::make(['profil' => $model->profil], [
                'profil' => 'nullable|array', 'profil.*' => 'nullable|string|max:200',
                'profil.tanggal_perkawinan' => 'nullable|date|before_or_equal:today',
                'profil.tanggal_perceraian' => 'nullable|date|before_or_equal:today',
                'profil.status_sekolah' => 'nullable|in:belum_sekolah,bersekolah,tidak_bersekolah',
                'profil.pencatatan_perkawinan' => 'nullable|in:tercatat,belum_tercatat,tidak_diketahui',
            ])->validate();
        }
        if ($model instanceof Anggota && $model->status_kawin !== 'cerai_hidup' && $model->profil) {
            $profil = $model->profil;
            unset($profil['tanggal_perceraian'], $profil['nomor_perceraian']);
            $model->profil = $profil;
        }
        if ($model instanceof Keluarga && (! $model->exists || $model->isDirty('id_wilayah'))) {
            self::wilayah($model->id_wilayah, 'rt');
        }
        if ($model instanceof Anggota && (! $model->exists || $model->isDirty('id_keluarga'))) {
            $k = Keluarga::find($model->id_keluarga);
            if (! $k) {
                throw ValidationException::withMessages(['id_keluarga' => 'Keluarga tidak tersedia.']);
            }
            self::wilayah($k->id_wilayah, 'rt');
        }
        if ($model instanceof User && (! $model->exists || $model->isDirty(['id_wilayah', 'role']))) {
            $level = $model->role?->levelWilayah();
            if ($level) {
                self::wilayah($model->id_wilayah, $level);
            } elseif ($model->id_wilayah !== null) {
                throw ValidationException::withMessages(['id_wilayah' => 'Admin global tidak memiliki wilayah penugasan.']);
            }
        }
    }
}
