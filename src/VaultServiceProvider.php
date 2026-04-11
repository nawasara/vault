<?php

namespace Nawasara\Vault;

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
