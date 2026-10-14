<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

// PRD F13/§8.9: mengisi created_by dan mencatat audit (aksi, tabel, id
// record, pengguna, waktu). Nilai kolom dan hash TIDAK PERNAH ditulis ke
// audit_log, sesuai §9.2.
class AuditObserver
{
    public function creating(Model $model): void
    {
        if ($model->isFillable('created_by') && auth()->hasUser()) {
            $model->created_by = auth()->id();
        }
    }

    public function created(Model $model): void
    {
        $this->log($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->log($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->log($model, 'deleted');
    }

    public function restored(Model $model): void
    {
        $this->log($model, 'restored');
    }

    private function log(Model $model, string $aksi): void
    {
        AuditLog::create([
            'id_pengguna' => auth()->id(),
            'aksi' => $aksi,
            'nama_tabel' => $model->getTable(),
            'id_record' => $model->getKey(),
        ]);
    }
}
