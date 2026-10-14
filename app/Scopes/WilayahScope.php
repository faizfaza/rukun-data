<?php

namespace App\Scopes;

use App\Models\Anggota;
use App\Support\WilayahAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

// PRD F2/§9.3: pembatasan wilayah otomatis di semua query. Dipasang sebagai
// global scope agar berlaku juga saat record di-resolve dari URL (akses
// wilayah lain menghasilkan 404, bukan kebocoran data).
class WilayahScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ids = WilayahAccess::allowedRtIds(auth()->user());

        if ($ids === null) {
            return;
        }

        if ($model instanceof Anggota) {
            $builder->whereHas('keluarga', function (Builder $query) use ($ids) {
                $query->whereIn('keluarga.id_wilayah', $ids);
            });

            return;
        }

        $builder->whereIn($model->getTable().'.id_wilayah', $ids);
    }
}
