<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Support\AuditFormatter;
use Illuminate\Database\Eloquent\Model;

// PRD F13/§8.9: mengisi created_by dan mencatat audit (aksi, tabel, id
// record, pengguna, waktu, serta rincian terenkripsi). Nilai kolom dan hash
// TIDAK PERNAH ditulis dalam bentuk plaintext ke audit_log (§9.2).
class AuditObserver
{
    /**
     * Menyimpan snapshot sebelum perubahan (keyed by table:id).
     *
     * @var array<string, array<string, string>>
     */
    protected static array $beforeSnapshots = [];

    public function creating(Model $model): void
    {
        if ($model->isFillable('created_by') && auth()->hasUser()) {
            $model->created_by = auth()->id();
        }
    }

    public function updating(Model $model): void
    {
        $this->captureBefore($model);
    }

    public function deleting(Model $model): void
    {
        $this->captureBefore($model);
    }

    public function restoring(Model $model): void
    {
        $this->captureBefore($model);
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

    private function captureBefore(Model $model): void
    {
        $id = $model->getKey();
        if (! $id) {
            return;
        }

        $key = $model->getTable().':'.$id;
        $beforeModel = AuditFormatter::findTargetModel($model->getTable(), (int) $id);
        if ($beforeModel !== null) {
            self::$beforeSnapshots[$key] = AuditFormatter::snapshotModel($beforeModel);
        }
    }

    private function log(Model $model, string $aksi): void
    {
        $id = $model->getKey();
        $key = $model->getTable().':'.$id;
        $sebelum = self::$beforeSnapshots[$key] ?? [];
        unset(self::$beforeSnapshots[$key]);

        $detail = null;
        try {
            $detail = AuditFormatter::buildPayload($model, $aksi, $sebelum);
        } catch (\Throwable) {
            $detail = null;
        }

        // Form KK menyimpan ulang seluruh kartu anggota/ekonomi/aset sekaligus.
        // Baris yang nilainya tidak berubah sama sekali tidak perlu dicatat,
        // supaya log hanya berisi aksi yang benar-benar dilakukan pengguna.
        if ($aksi === 'updated'
            && in_array($model->getTable(), ['keluarga', 'anggota', 'aset_keluarga', 'ekonomi_keluarga'], true)
            && $sebelum !== []
            && is_array($detail)
            && empty($detail['perubahan'])) {
            return;
        }

        AuditLog::create([
            'id_pengguna' => auth()->id(),
            'aksi' => $aksi,
            'nama_tabel' => $model->getTable(),
            'id_record' => $id,
            'detail' => $detail,
        ]);
    }
}
