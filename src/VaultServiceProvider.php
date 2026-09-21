<?php

namespace Nawasara\Vault;

use Illuminate\Console\Scheduling\Schedule;
use Livewire\Livewire;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Illuminate\Support\ServiceProvider;
use Nawasara\Vault\Services\VaultManager;

class VaultServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'nawasara-vault');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->registerLivewire();
        $this->offerPublishing();
        $this->registerMinioDisk();
        $this->registerSchedule();

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Nawasara\Vault\Console\Commands\PruneAccessLogCommand::class,
            ]);
        }
    }

    /**
     * Jadwalkan pembersihan access log.
     *
     * ⚠️ `retention_days` sudah ada di config sejak paket ini dibuat, tetapi
     * tidak pernah ada yang menjalankannya. Tabelnya tumbuh sampai 5,9 juta
     * baris sebelum ketahuan, dan `count()` atasnya butuh satu detik penuh.
     *
     * Dipanggil lewat `$schedule->call()` yang memanggil Artisan, BUKAN
     * `$schedule->command()`: perintah yang didaftarkan lewat `$this->commands()`
     * di sebuah paket tidak selalu muncul di kernel Artisan saat scheduler
     * boot, dan kalau tidak muncul, jadwalnya diam saja tanpa galat
     * (AGENTS.md §7).
     *
     * Jam 03.10 waktu Jakarta, bukan UTC: `app.timezone` di sini UTC, jadi
     * jadwal jam dinding tanpa `->timezone()` meleset tujuh jam dan berjalan
     * di tengah jam sibuk.
     */
    protected function registerSchedule(): void
    {
        $this->app->booted(function () {
            if (! $this->app->runningInConsole()) {
                return;
            }

            if (! config('nawasara-vault.prune.enabled', true)) {
                return;
            }

            $this->app->make(Schedule::class)
                ->call(fn () => \Illuminate\Support\Facades\Artisan::call('nawasara-vault:prune-access-log'))
                ->name('nawasara-vault:prune-access-log')
                ->cron((string) config('nawasara-vault.prune.cron', '10 3 * * *'))
                ->timezone('Asia/Jakarta')
                ->withoutOverlapping(30);
        });
    }

    /**
     * Daftarkan disk `minio` yang kredensialnya diambil dari Vault.
     *
     * Didaftarkan sebagai driver kustom, bukan entri statis di
     * `config/filesystems.php`, karena config di-cache di produksi: kunci yang
     * baru diganti admin tidak akan terbaca sampai cache dibersihkan, dan itu
     * membuat rotasi kunci tampak tidak berpengaruh.
     *
     * Tetap bernama `minio` supaya `Storage::disk('minio')` bekerja seperti
     * biasa — termasuk untuk baris `nawasara_aspirations_attachments` lama yang
     * sudah menyimpan nama disk itu di kolomnya.
     */
    protected function registerMinioDisk(): void
    {
        \Illuminate\Support\Facades\Storage::extend('minio', function ($app, array $config) {
            $bucket = $config['bucket'] ?? null;

            return \Nawasara\Vault\Services\MinioDisk::make($bucket);
        });
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/nawasara-vault.php', 'nawasara-vault');

        $this->app->singleton('nawasara.vault', function ($app) {
            return new VaultManager();
        });
    }

    public function registerLivewire(): void
    {
        $namespace = 'Nawasara\\Vault\\Livewire';
        $basePath = __DIR__.'/Livewire';

        if (! is_dir($basePath)) {
            return;
        }

        $finder = new Finder();
        $finder->files()->in($basePath)->name('*.php');

        foreach ($finder as $file) {
            $relativePath = str_replace('/', '\\', $file->getRelativePathname());
            $class = $namespace.'\\'.Str::beforeLast($relativePath, '.php');

            if (class_exists($class)) {
                $alias = 'nawasara-vault.'.
                    Str::of($relativePath)
                        ->replace('.php', '')
                        ->replace('\\', '.')
                        ->replace('/', '.')
                        ->explode('.')
                        ->map(fn ($segment) => Str::kebab($segment))
                        ->join('.');

                Livewire::component($alias, $class);
            }
        }
    }

    protected function offerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/nawasara-vault.php' => config_path('nawasara-vault.php'),
        ], 'nawasara-vault:config');
    }
}
