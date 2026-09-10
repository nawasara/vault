<?php

namespace Nawasara\Vault\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Setiap aksi yang menyentuh kredensial harus digerbang sudo.
 *
 * Brankas ini memuat kredensial yang menguasai seluruh infrastruktur —
 * root@pam Proxmox, admin Keycloak, pangkalan data produksi. Permission saja
 * tidak cukup: sesi yang ditinggalkan terbuka di komputer bersama sudah
 * memberi akses penuh kepada siapa pun yang lewat.
 *
 * ⚠️ Yang paling mudah salah adalah MEMILIH pintu yang digerbang.
 *
 * `toggleReveal()` terlihat seperti tempat yang benar — namanya saja
 * "tampilkan". Tetapi tombol mata di blade murni Alpine
 * (`show ? 'text' : 'password'`) dan tidak pernah memanggilnya. Yang
 * benar-benar mengeluarkan sandi adalah `openGroup()`, yang memuatnya ke
 * `$this->fields` sehingga ia langsung berada di snapshot Livewire — terbaca
 * dari devtools tanpa menekan apa pun.
 *
 * Menggerbang toggleReveal() saja berarti menjaga pintu yang tidak dilewati
 * siapa pun: tampak aman saat dibaca, tidak menahan apa-apa saat dijalankan.
 */
class SudoGateCoverageTest extends TestCase
{
    /** Metode yang WAJIB membawa #[RequiresSudo]. */
    private const WAJIB_DIGERBANG = [
        // Membuka modal = memuat nilai kredensial ke snapshot Livewire.
        'openGroup',
        'addInstance',
        'toggleReveal',
        'save',
        'deleteInstance',
    ];

    private function metodeBerSudo(): array
    {
        $kelas = \Nawasara\Vault\Livewire\Credential\Section\Table::class;

        if (! class_exists($kelas)) {
            $this->markTestSkipped('Komponen Vault tidak ter-autoload di lingkungan uji ini.');
        }

        $hasil = [];
        foreach ((new ReflectionClass($kelas))->getMethods() as $m) {
            foreach ($m->getAttributes() as $attr) {
                if (str_contains($attr->getName(), 'RequiresSudo')) {
                    $hasil[] = $m->getName();
                }
            }
        }

        return $hasil;
    }

    /**
     * Inti perkaranya: tidak satu pun aksi kredensial boleh tanpa gerbang.
     */
    public function test_semua_aksi_kredensial_digerbang(): void
    {
        $berSudo = $this->metodeBerSudo();

        foreach (self::WAJIB_DIGERBANG as $metode) {
            $this->assertContains(
                $metode,
                $berSudo,
                "{$metode}() menyentuh kredensial tanpa gerbang sudo",
            );
        }
    }

    /**
     * openGroup() secara khusus — inilah pintu yang sebenarnya.
     *
     * Diuji terpisah dari yang lain supaya bila suatu saat seseorang merapikan
     * daftar di atas, yang satu ini tetap punya alasannya sendiri yang tertulis.
     */
    public function test_openGroup_digerbang_karena_memuat_sandi_ke_snapshot(): void
    {
        $this->assertContains(
            'openGroup',
            $this->metodeBerSudo(),
            'openGroup() memuat sandi ke snapshot Livewire — menggerbang toggleReveal() saja tidak menahan apa pun',
        );
    }

    /** Komponen memakai trait WithSudo, tanpa itu gerbangnya tidak berfungsi. */
    public function test_komponen_memakai_trait_with_sudo(): void
    {
        $kelas = \Nawasara\Vault\Livewire\Credential\Section\Table::class;

        if (! class_exists($kelas)) {
            $this->markTestSkipped('Komponen Vault tidak ter-autoload di lingkungan uji ini.');
        }

        $traits = class_uses_recursive($kelas);

        $this->assertTrue(
            (bool) collect($traits)->first(fn ($t) => str_contains($t, 'WithSudo')),
            'tanpa trait WithSudo, atribut RequiresSudo tidak menggiring pengguna ke step-up',
        );
    }
}
