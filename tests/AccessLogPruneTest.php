<?php

namespace Nawasara\Vault\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Nawasara\Vault\Facades\Vault;
use Tests\TestCase;

/**
 * Cache pembacaan kredensial dan pembersihan access log.
 *
 * Menjaga perbaikan 21 September 2026. Sebelum itu tiap `Vault::get()`
 * menembak basis data TIGA kali (SELECT, UPDATE last_accessed_at, INSERT log)
 * dan tidak ada cache sama sekali. Karena klien memanggil Vault sekali per
 * FIELD dan sync berjalan tiap beberapa menit, hasilnya 53 baris log per
 * menit; `nawasara_vault_access_log` tumbuh ke 5,9 juta baris dan `count()`
 * atasnya butuh satu detik.
 *
 * `retention_days` sudah ada di config sejak awal tetapi tak pernah ada yang
 * menjalankannya.
 */
class AccessLogPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['nawasara-vault.cache_ttl' => 60]);
        app('cache')->flush();
    }

    public function test_pembacaan_berulang_tidak_menambah_log(): void
    {
        Vault::set('uji', 'host', '10.1.1.23');

        $sebelum = DB::table('nawasara_vault_access_log')->where('action', 'read')->count();

        for ($i = 0; $i < 10; $i++) {
            $this->assertSame('10.1.1.23', Vault::get('uji', 'host'));
        }

        $sesudah = DB::table('nawasara_vault_access_log')->where('action', 'read')->count();

        // Satu baca pertama boleh tercatat; sembilan sisanya dari cache.
        $this->assertLessThanOrEqual(1, $sesudah - $sebelum);
    }

    /**
     * Rotasi harus berlaku SEKETIKA.
     *
     * Ini yang membuat cache aman dipasang: kalau nilai baru baru berlaku
     * setelah TTL habis, admin yang mengganti kredensial akan mengira
     * perubahannya tidak tersimpan.
     */
    public function test_rotasi_berlaku_seketika(): void
    {
        Vault::set('uji', 'host', 'lama');
        $this->assertSame('lama', Vault::get('uji', 'host'));

        Vault::set('uji', 'host', 'baru');
        $this->assertSame('baru', Vault::get('uji', 'host'));
    }

    public function test_hapus_juga_membuang_cache(): void
    {
        Vault::set('uji', 'host', 'x');
        Vault::get('uji', 'host');

        Vault::delete('uji', 'host');

        $this->assertNull(Vault::get('uji', 'host'));
    }

    public function test_cache_ttl_nol_mematikan_cache(): void
    {
        config(['nawasara-vault.cache_ttl' => 0]);
        Vault::set('uji', 'host', 'x');

        $sebelum = DB::table('nawasara_vault_access_log')->where('action', 'read')->count();
        for ($i = 0; $i < 3; $i++) { Vault::get('uji', 'host'); }
        $sesudah = DB::table('nawasara_vault_access_log')->where('action', 'read')->count();

        $this->assertSame(3, $sesudah - $sebelum);
    }

    public function test_prune_menghapus_yang_tua_saja(): void
    {
        Vault::set('uji', 'host', 'x');
        $id = DB::table('nawasara_vault_credentials')->value('id');

        foreach ([120, 120, 95] as $hari) {
            DB::table('nawasara_vault_access_log')->insert([
                'credential_id' => $id, 'action' => 'read', 'accessor' => 'system',
                'created_at' => now()->subDays($hari),
            ]);
        }
        foreach ([1, 30] as $hari) {
            DB::table('nawasara_vault_access_log')->insert([
                'credential_id' => $id, 'action' => 'read', 'accessor' => 'system',
                'created_at' => now()->subDays($hari),
            ]);
        }

        Artisan::call('nawasara-vault:prune-access-log', ['--days' => 90]);

        $this->assertSame(0, DB::table('nawasara_vault_access_log')
            ->where('created_at', '<', now()->subDays(90))->count());

        $this->assertGreaterThanOrEqual(2, DB::table('nawasara_vault_access_log')
            ->where('created_at', '>=', now()->subDays(90))->count());
    }

    public function test_dry_run_tidak_menghapus(): void
    {
        Vault::set('uji', 'host', 'x');
        $id = DB::table('nawasara_vault_credentials')->value('id');

        DB::table('nawasara_vault_access_log')->insert([
            'credential_id' => $id, 'action' => 'read', 'accessor' => 'system',
            'created_at' => now()->subDays(200),
        ]);

        $sebelum = DB::table('nawasara_vault_access_log')->count();

        Artisan::call('nawasara-vault:prune-access-log', ['--days' => 90, '--dry-run' => true]);

        $this->assertSame($sebelum, DB::table('nawasara_vault_access_log')->count());
    }

    /**
     * Masa simpan 0 berarti "jangan hapus apa pun", bukan "hapus semua".
     *
     * Disetel lewat CONFIG, bukan `--days => 0`: Laravel memperlakukan opsi
     * bernilai 0 sebagai tidak diisi, sehingga perintahnya justru jatuh ke
     * nilai config. Yang diuji di sini penjaga di dalam perintahnya.
     */
    public function test_retensi_nol_tidak_menghapus(): void
    {
        config(['nawasara-vault.retention_days' => 0]);

        Vault::set('uji', 'host', 'x');
        $id = DB::table('nawasara_vault_credentials')->value('id');

        DB::table('nawasara_vault_access_log')->insert([
            'credential_id' => $id, 'action' => 'read', 'accessor' => 'system',
            'created_at' => now()->subDays(500),
        ]);

        $sebelum = DB::table('nawasara_vault_access_log')->count();

        Artisan::call('nawasara-vault:prune-access-log');

        $this->assertSame($sebelum, DB::table('nawasara_vault_access_log')->count());
    }
}
