<?php

namespace Nawasara\Vault\Services;

use Aws\S3\S3Client;
use Nawasara\Vault\Facades\Vault;

/**
 * Penguji koneksi MinIO untuk tombol "Test" di panel Vault.
 *
 * Berada di paket vault, bukan di paket yang memakainya, karena kredensialnya
 * milik server — bukan milik aspirations maupun paket lain mana pun. Menaruh
 * penguji di salah satu paket konsumen berarti tombol Test mati begitu paket
 * itu dicopot.
 */
class MinioTester
{
    /**
     * Kontrak Vault: WAJIB mengembalikan kunci `success`, bukan `ok`.
     * Panel membaca `$hasil['success'] ?? false`; memakai `ok` menghasilkan
     * toast merah meski pesannya berbunyi "Terhubung".
     */
    public function testConnection(): array
    {
        $endpoint = trim((string) Vault::get('minio', 'endpoint'));
        $key = (string) Vault::get('minio', 'access_key');
        $secret = (string) Vault::get('minio', 'secret_key');
        $bucket = trim((string) Vault::get('minio', 'bucket')) ?: 'nawasara';
        $region = trim((string) Vault::get('minio', 'region')) ?: 'us-east-1';

        if ($endpoint === '' || $key === '' || $secret === '') {
            return [
                'success' => false,
                'message' => 'Endpoint, Access Key, dan Secret Key wajib diisi.',
            ];
        }

        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'endpoint' => rtrim($endpoint, '/'),
                'credentials' => ['key' => $key, 'secret' => $secret],

                // WAJIB true untuk MinIO. AWS memakai gaya virtual-host
                // (bucket.host); MinIO memakai gaya path (host/bucket). Bila
                // salah, permintaan menuju alamat yang tidak ada dan galatnya
                // menyesatkan — tampak seperti kredensial yang salah.
                'use_path_style_endpoint' => true,

                'http' => ['timeout' => 10, 'connect_timeout' => 5],
            ]);

            // Menulis lalu menghapus, bukan sekadar listBuckets(). Kredensial
            // yang hanya boleh membaca akan lolos daftar-bucket tetapi gagal
            // saat foto pertama diunggah — dan itu baru ketahuan dari warga.
            $probe = 'nawasara-vault-test-'.bin2hex(random_bytes(6));

            $client->putObject([
                'Bucket' => $bucket,
                'Key' => $probe,
                'Body' => 'uji koneksi nawasara',
            ]);

            $client->deleteObject(['Bucket' => $bucket, 'Key' => $probe]);

            return [
                'success' => true,
                'message' => "Terhubung — bucket '{$bucket}' dapat ditulis dan dihapus.",
            ];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            return [
                'success' => false,
                'message' => $this->explain($e, $bucket),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Gagal: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Menerjemahkan galat AWS menjadi kalimat yang menyebut langkah berikutnya.
     * Pesan mentahnya menyebut istilah S3 yang tidak membantu admin.
     */
    protected function explain(\Aws\S3\Exception\S3Exception $e, string $bucket): string
    {
        return match ($e->getAwsErrorCode()) {
            'NoSuchBucket' => "Bucket '{$bucket}' tidak ada di server. Buat dulu lewat konsol MinIO.",
            'InvalidAccessKeyId' => 'Access Key tidak dikenali server.',
            'SignatureDoesNotMatch' => 'Secret Key tidak cocok.',
            'AccessDenied' => "Kredensial dikenali, tetapi tidak berhak menulis ke bucket '{$bucket}'.",
            default => 'Gagal: '.($e->getAwsErrorMessage() ?: $e->getMessage()),
        };
    }
}
