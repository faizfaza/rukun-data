<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Keluarga;
use App\Support\SensitiveData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// PRD §14.3 + §9.2: kolom sensitif tidak terbaca plaintext di database,
// dekripsi round-trip benar, dan audit_log bebas nilai sensitif maupun hash.
class EncryptionAtRestTest extends TestCase
{
    use FixturesRukunData;
    use RefreshDatabase;

    public function test_kolom_sensitif_tersimpan_terenkripsi(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);

        $this->actingAs($admin);
        $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $admin->id);

        $raw = DB::selectOne('SELECT no_kk, no_kk_hash FROM keluarga LIMIT 1');

        $this->assertStringNotContainsString('6371010101000001', $raw->no_kk);
        $this->assertSame(64, strlen($raw->no_kk_hash));
        $this->assertSame(
            hash_hmac('sha256', '6371010101000001', config('sensitif.hmac_key')),
            $raw->no_kk_hash
        );

        $this->assertSame('6371010101000001', Keluarga::first()->no_kk);
    }

    public function test_audit_log_tidak_menyimpan_nilai_sensitif(): void
    {
        $w = $this->seedDasar();
        $admin = $this->makeUser('admin', 'superadmin', null);

        $this->actingAs($admin);
        $this->makeKeluarga($w['rt1']->id, '6371010101000001', [
            ['nik' => '6371010101800001', 'hubungan' => 1],
        ], $admin->id);

        $this->assertGreaterThan(0, AuditLog::count());

        foreach (AuditLog::all() as $log) {
            $serial = json_encode($log->getAttributes());
            $this->assertStringNotContainsString('6371010101000001', $serial);
            $this->assertStringNotContainsString('6371010101800001', $serial);
            $this->assertStringNotContainsString(SensitiveData::hash('6371010101000001'), $serial);
        }
    }
}
