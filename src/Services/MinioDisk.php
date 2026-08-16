<?php

namespace Nawasara\Vault\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Nawasara\Vault\Facades\Vault;

/**
 * Membangun disk MinIO dari kredensial di Vault.
 *
 * Kredensial SERVER tersimpan sekali di Vault; nama BUCKET ditentukan paket
 * yang memanggil. Satu server, banyak bucket — aspirations menulis ke
 * buckets-nya sendiri, paket lain ke miliknya, dengan kunci yang sama.
 *
 * ── Kenapa tidak lewat .env ──────────────────────────────────────────────
 *
 * Kredensial di .env berarti mengubahnya butuh akses server dan deploy ulang,
 * dan nilainya tidak pernah terlihat siapa pun kecuali yang bisa masuk ke
 * mesinnya. Vault memberi admin cara mengganti kunci yang bocor pada hari itu
 * juga, lengkap dengan tombol uji koneksi dan catatan siapa mengubah apa.
 *
 * Disk dibangun saat dipakai (`Storage::build`), bukan didaftarkan di
 * `config/filesystems.php`, karena config di-cache di produksi — kredensial
 * yang baru diganti admin tidak akan terbaca sampai cache dibersihkan.
 */
class MinioDisk
{
    /** Cache per-permintaan; membangun ulang tiap panggilan itu mubazir. */
    protected static array $cache = [];

    /**
     * @param  string|null  $bucket  Bucket yang dituju. Null memakai bucket
     *                               bawaan yang disetel di Vault.
     */
    public static function make(?string $bucket = null): Filesystem
    {
        $bucket = $bucket ?: (string) Vault::get('minio', 'bucket') ?: 'nawasara';

        if (isset(self::$cache[$bucket])) {
            return self::$cache[$bucket];
        }

        return self::$cache[$bucket] = Storage::build([
            'driver' => 's3',
            'key' => (string) Vault::get('minio', 'access_key'),
            'secret' => (string) Vault::get('minio', 'secret_key'),
            'region' => (string) Vault::get('minio', 'region') ?: 'us-east-1',
            'bucket' => $bucket,
            'endpoint' => rtrim((string) Vault::get('minio', 'endpoint'), '/'),

            // WAJIB true — MinIO memakai gaya path (host/bucket), bukan gaya
            // virtual-host milik AWS (bucket.host). Bila salah, setiap
            // permintaan menuju alamat yang tidak ada dan galatnya menyerupai
            // kredensial yang keliru.
            'use_path_style_endpoint' => true,

            // Melempar, bukan mengembalikan false. Unggahan yang gagal diam-diam
            // menghasilkan laporan warga dengan foto yang tidak pernah ada, dan
            // tidak ada yang menyadarinya sampai Kabid membuka laporan itu.
            'throw' => true,
        ]);
    }

    /** Apakah kredensialnya sudah diisi admin? */
    public static function configured(): bool
    {
        return trim((string) Vault::get('minio', 'endpoint')) !== ''
            && trim((string) Vault::get('minio', 'access_key')) !== ''
            && trim((string) Vault::get('minio', 'secret_key')) !== '';
    }

    /** Dipakai pengujian agar kredensial yang diganti tidak tertahan cache. */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
