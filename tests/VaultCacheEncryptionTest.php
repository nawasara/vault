<?php

namespace Nawasara\Vault\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Nawasara\Vault\Facades\Vault;
use Tests\TestCase;

/**
 * Audit keamanan 15 Oktober 2026 (S15 dan T7).
 *
 * S15: cache Vault dulu menyimpan plaintext. Di prod cache itu Redis tanpa
 * sandi, di dev tabel `cache` yang ikut ke backup DB. Sekarang yang masuk
 * cache harus ciphertext.
 *
 * T7: pembukaan nilai oleh manusia di panel harus tercatat sebagai `reveal`.
 */
class VaultCacheEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI_CACHE = 'nawasara-vault:enc:uji:password:-';

    protected function setUp(): void
    {
        parent::setUp();

        config(['nawasara-vault.cache_ttl' => 60]);
        app('cache')->flush();
    }

    public function test_cache_tidak_menyimpan_plaintext(): void
    {
        Vault::set('uji', 'password', 'rahasia-sekali');

        $this->assertSame('rahasia-sekali', Vault::get('uji', 'password'));

        $isi = Cache::get(self::KUNCI_CACHE);

        $this->assertIsString($isi);
        $this->assertStringNotContainsString('rahasia-sekali', $isi);
        $this->assertSame('rahasia-sekali', Crypt::decryptString($isi));
    }

    public function test_pembacaan_kedua_dari_cache_tetap_benar(): void
    {
        Vault::set('uji', 'password', 'abc');

        $this->assertSame('abc', Vault::get('uji', 'password'));
        $this->assertSame('abc', Vault::get('uji', 'password'));
    }

    public function test_isi_cache_rusak_diambil_ulang_dari_db(): void
    {
        Vault::set('uji', 'password', 'abc');
        Cache::put(self::KUNCI_CACHE, 'bukan-ciphertext', 60);

        $this->assertSame('abc', Vault::get('uji', 'password'));
    }

    public function test_reveal_mencatat_access_log(): void
    {
        Vault::set('uji', 'password', 'abc');

        $this->assertSame('abc', Vault::reveal('uji', 'password'));
        $this->assertSame(1, DB::table('nawasara_vault_access_log')->where('action', 'reveal')->count());
        $this->assertNull(Vault::reveal('uji', 'tidak-ada'));
    }
}
