<?php

namespace Nawasara\Vault\Tests;

use Nawasara\Vault\Livewire\Credential\Section\Table;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Audit keamanan 15 Oktober 2026 (T7): modal kredensial tidak lagi memuat
 * nilai tersimpan, jadi save() harus menulis HANYA field yang benar-benar
 * diubah pengguna. Kalau salah, simpan formulir tanpa mengetik apa pun akan
 * menimpa sandi dengan string kosong atau menulis ulang semua select.
 */
class CredentialFieldsToWriteTest extends TestCase
{
    protected function setUp(): void
    {
        if (! class_exists(Table::class)) {
            $this->markTestSkipped('Komponen Vault tidak ter-autoload di lingkungan uji ini.');
        }
    }

    public function test_field_kosong_berarti_tidak_diubah(): void
    {
        $fields = [
            'host' => ['value' => '', 'has_value' => true, 'original' => null],
            'password' => ['value' => '', 'has_value' => true, 'original' => null],
        ];

        $this->assertSame([], Table::fieldsToWrite($fields));
    }

    public function test_field_yang_diketik_ditulis(): void
    {
        $fields = [
            'host' => ['value' => '10.1.1.23', 'has_value' => true, 'original' => null],
            'password' => ['value' => '', 'has_value' => true, 'original' => null],
            'port' => ['value' => '0', 'has_value' => false, 'original' => null],
        ];

        $this->assertSame(['host' => '10.1.1.23', 'port' => '0'], Table::fieldsToWrite($fields));
    }

    public function test_select_tidak_berubah_tidak_ditulis(): void
    {
        $fields = [
            'mode' => ['value' => 'tls', 'has_value' => true, 'original' => 'tls'],
            'region' => ['value' => 'b', 'has_value' => true, 'original' => 'a'],
            // Select belum tersimpan: opsi pertama tetap ditulis, sama seperti dulu.
            'scheme' => ['value' => 'https', 'has_value' => false, 'original' => null],
        ];

        $this->assertSame(['region' => 'b', 'scheme' => 'https'], Table::fieldsToWrite($fields));
    }

    public function test_isian_bukan_skalar_diabaikan(): void
    {
        $fields = [
            'host' => ['value' => ['x'], 'original' => null],
            'rusak' => 'bukan-array',
        ];

        $this->assertSame([], Table::fieldsToWrite($fields));
    }

    /** Tidak boleh ada lagi properti publik yang menampung nilai yang dibuka. */
    public function test_tidak_ada_properti_publik_revealed(): void
    {
        $this->assertFalse((new ReflectionClass(Table::class))->hasProperty('revealed'));
    }
}
