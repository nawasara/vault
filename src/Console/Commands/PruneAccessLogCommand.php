<?php

namespace Nawasara\Vault\Console\Commands;

use Illuminate\Console\Command;
use Nawasara\Vault\Models\AccessLog;

/**
 * Hapus catatan akses Vault yang lebih tua dari masa simpan.
 *
 * ⚠️ `retention_days` sudah ada di config sejak awal, tetapi TIDAK ADA satu
 * pun yang menjalankannya. Akibatnya tabel tumbuh sampai 5,9 juta baris
 * (~90 ribu per hari) dan `count()` atasnya butuh satu detik penuh, yang
 * terasa di setiap halaman panel yang menampilkan jumlah.
 *
 * Dihapus BERTAHAP, bukan satu DELETE besar. Menghapus jutaan baris sekaligus
 * mengunci tabel lama dan membengkakkan undo log; pembacaan kredensial ikut
 * tertahan selama itu, dan setiap sync di seluruh Nawasara ikut menunggu.
 */
class PruneAccessLogCommand extends Command
{
    protected $signature = 'nawasara-vault:prune-access-log
        {--days= : Masa simpan dalam hari, menimpa config}
        {--chunk=5000 : Jumlah baris per putaran}
        {--dry-run : Hitung saja, tidak menghapus apa pun}';

    protected $description = 'Prune Vault access log entries older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('nawasara-vault.retention_days', 90));

        if ($days <= 0) {
            $this->warn('Masa simpan 0 atau kurang, pembersihan dilewati.');

            return self::SUCCESS;
        }

        $batas = now()->subDays($days);
        $chunk = max(100, (int) $this->option('chunk'));

        $jumlah = AccessLog::where('created_at', '<', $batas)->count();

        $this->info("Masa simpan {$days} hari, batas {$batas->toDateTimeString()}.");
        $this->info('Baris yang memenuhi syarat: '.number_format($jumlah));

        if ($jumlah === 0) {
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->comment('Dry run, tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $terhapus = 0;

        do {
            $n = AccessLog::where('created_at', '<', $batas)->limit($chunk)->delete();
            $terhapus += $n;

            if ($n > 0) {
                $this->line('  '.number_format($terhapus).' / '.number_format($jumlah));
                // Beri napas pada replikasi dan pembacaan lain.
                usleep(100000);
            }
        } while ($n > 0);

        $this->info('Selesai, '.number_format($terhapus).' baris dihapus.');

        /*
         * Ruangnya TIDAK otomatis kembali ke sistem berkas. InnoDB menyimpannya
         * sebagai ruang bebas di dalam tabel dan memakainya lagi untuk baris
         * berikutnya, jadi untuk tabel yang terus diisi itu justru yang
         * diinginkan. `OPTIMIZE TABLE` mengunci tabel dan hanya sepadan setelah
         * penghapusan besar sekali waktu, bukan sebagai bagian dari cron.
         */
        $this->comment('Ruang dipakai ulang InnoDB. Jalankan OPTIMIZE TABLE manual bila memang perlu dikembalikan ke disk.');

        return self::SUCCESS;
    }
}
