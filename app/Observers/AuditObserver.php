<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Keluarga;
use App\Models\ValidasiPerubahan;
use App\Models\Wilayah;
use App\Support\AuditFormatter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Menyimpan atribut mentah DB sebelum perubahan untuk keperluan rollback validasi.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $rawBeforeAttributes = [];

    /**
     * Menyimpan ID tiket ValidasiPerubahan yang baru dibuat pada request saat ini
     * untuk mengarahkan pengguna (Admin RT/RW) ke halaman konfirmasi pengajuan.
     *
     * @var list<int>
     */
    protected static array $lastCreatedTicketIds = [];

    /**
     * Ambil sekaligus bersihkan daftar ID tiket validasi yang dibuat pada aksi terakhir.
     *
     * @return list<int>
     */
    public static function popLastCreatedTicketIds(): array
    {
        $ids = array_values(array_unique(self::$lastCreatedTicketIds));
        self::$lastCreatedTicketIds = [];

        return $ids;
    }

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
            self::$rawBeforeAttributes[$key] = $beforeModel->getRawOriginal();
        }
    }

    private function log(Model $model, string $aksi): void
    {
        $id = $model->getKey();
        $key = $model->getTable().':'.$id;
        $sebelum = self::$beforeSnapshots[$key] ?? [];
        $rawBefore = self::$rawBeforeAttributes[$key] ?? [];
        unset(self::$beforeSnapshots[$key], self::$rawBeforeAttributes[$key]);

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
            'waktu' => now(),
        ]);

        $this->catatValidasiBerjenjang($model, $aksi, is_array($detail) ? $detail : [], $rawBefore);
    }

    /**
     * Mencatat antrean validasi/approval berjenjang (RT -> RW -> Desa)
     * apabila aksi dilakukan oleh Admin RT atau Admin RW.
     *
     * @param  array<string, mixed>  $detail
     * @param  array<string, mixed>  $rawBefore
     */
    private function catatValidasiBerjenjang(Model $model, string $aksi, array $detail, array $rawBefore): void
    {
        if (! in_array($model->getTable(), ['keluarga', 'anggota', 'aset_keluarga', 'ekonomi_keluarga'], true)) {
            return;
        }

        $user = auth()->user();
        if (! $user || (! $user->isAdminRt() && ! $user->isAdminRw())) {
            return;
        }

        try {
            if (! Schema::hasTable('validasi_perubahan')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $konteks = $detail['konteks'] ?? [];
        $idKeluarga = $konteks['id_keluarga'] ?? ($model instanceof Keluarga ? $model->id : ($model->id_keluarga ?? null));
        $idWilayahRt = $konteks['id_wilayah'] ?? null;

        if (! $idWilayahRt && $idKeluarga) {
            $idWilayahRt = Keluarga::withoutGlobalScopes()->withTrashed()->whereKey($idKeluarga)->value('id_wilayah');
        }

        $rtWilayah = $idWilayahRt ? Wilayah::withoutGlobalScopes()->find($idWilayahRt) : null;
        $rwWilayah = $rtWilayah?->rwInduk();
        $desaWilayah = $rtWilayah?->desaInduk() ?? $user->desaWilayah();

        // Cek kebijakan desa apakah validasi berjenjang diaktifkan (default: true)
        if ($desaWilayah && $desaWilayah->pengaturanDesa('wajib_validasi_berjenjang', true) === false) {
            return;
        }

        // Jika baru saja membuat KK baru, anggota/aset/ekonomi bawaan KK baru digabung ke tiket KK tersebut
        if ($aksi === 'created' && $model->getTable() !== 'keluarga' && $idKeluarga) {
            $existingKkTicket = ValidasiPerubahan::where('nama_tabel', 'keluarga')
                ->where('id_record', $idKeluarga)
                ->where('aksi', 'created')
                ->where('created_at', '>=', now()->subSeconds(10))
                ->latest('id')
                ->first();

            if ($existingKkTicket) {
                $kkFresh = Keluarga::withoutGlobalScopes()->withTrashed()->find($idKeluarga);
                if ($kkFresh) {
                    $freshPayload = AuditFormatter::buildPayload($kkFresh, 'created', []);
                    $existingKkTicket->update([
                        'ringkasan' => $freshPayload['ringkasan'] ?? $existingKkTicket->ringkasan,
                        'payload' => array_merge($existingKkTicket->payloadData, $freshPayload),
                    ]);
                }

                return;
            }
        }

        // Deteksi apakah perubahan merupakan mutasi/pindah
        $aksiValidasi = $aksi;
        if ($aksi === 'updated') {
            foreach ($detail['perubahan'] ?? [] as $item) {
                $label = strtolower((string) ($item['label'] ?? ''));
                $sesudahVal = strtolower((string) ($item['sesudah'] ?? ''));
                if (
                    ($label === 'status kependudukan' && str_contains($sesudahVal, 'pindah'))
                    || $label === 'wilayah rt / rw'
                ) {
                    $aksiValidasi = 'pindah';
                    break;
                }
            }
        }

        $statusAwal = $user->isAdminRt()
            ? ValidasiPerubahan::STATUS_MENUNGGU_RW
            : ValidasiPerubahan::STATUS_MENUNGGU_DESA;

        $rawBeforeFull = $rawBefore;
        $rawAfter = $model->getAttributes();
        unset($rawBefore['id'], $rawBefore['created_at'], $rawBefore['updated_at']);
        unset($rawAfter['id'], $rawAfter['created_at'], $rawAfter['updated_at']);

        $tiket = ValidasiPerubahan::create([
            'id_pengguna' => $user->id,
            'peran_pengaju' => $user->role?->value,
            'aksi' => $aksiValidasi,
            'nama_tabel' => $model->getTable(),
            'id_record' => (int) $model->getKey(),
            'id_keluarga' => $idKeluarga ? (int) $idKeluarga : null,
            'id_wilayah_rt' => $rtWilayah?->id,
            'id_wilayah_rw' => $rwWilayah?->id,
            'id_wilayah_desa' => $desaWilayah?->id,
            'status' => $statusAwal,
            'ringkasan' => $detail['ringkasan'] ?? "Pengajuan {$aksiValidasi} pada {$model->getTable()}",
            'payload' => array_merge($detail, [
                'raw_before' => $rawBefore,
                'raw_after' => $rawAfter,
                'deferred' => true,
            ]),
        ]);

        self::$lastCreatedTicketIds[] = (int) $tiket->id;

        // Tahan perubahan (staged): data resmi TIDAK langsung berubah sebelum disahkan oleh Kepala Desa.
        // Tulis langsung ke DB menggunakan nilai mentah agar kolom terenkripsi tidak terenkripsi ganda.
        if (in_array($aksi, ['updated', 'pindah'], true) && $rawBefore !== []) {
            DB::table($model->getTable())
                ->where($model->getKeyName(), $model->getKey())
                ->update($rawBefore);
            $model->setRawAttributes(array_merge($model->getAttributes(), $rawBefore), true);
        } elseif ($aksi === 'deleted') {
            if (in_array($model->getTable(), ['keluarga', 'anggota'], true) && method_exists($model, 'restore')) {
                Model::withoutEvents(fn () => $model->restore());
            } elseif (in_array($model->getTable(), ['aset_keluarga', 'ekonomi_keluarga'], true) && $rawBeforeFull !== []) {
                DB::table($model->getTable())->insert($rawBeforeFull);
            }
        }

        try {
            \Filament\Notifications\Notification::make()
                ->title($user->isAdminRt()
                    ? 'Pengajuan Perubahan Terkirim ke Admin RW'
                    : 'Pengajuan Perubahan Terkirim ke Kepala Desa')
                ->body($user->isAdminRt()
                    ? 'Data utama belum diubah sampai disetujui oleh Admin RW dan disahkan oleh Kepala Desa.'
                    : 'Data utama belum diubah sampai disahkan oleh Admin Desa / Kepala Desa.')
                ->info()
                ->send();
        } catch (\Throwable) {
            // Abaikan jika dijalankan di luar siklus session/HTTP
        }
    }
}
