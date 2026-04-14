<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Vault\Livewire\Credential\Index as CredentialIndex;
use Nawasara\Vault\Livewire\AccessLog\Index as AccessLogIndex;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::middleware(['web', 'auth'])->prefix('nawasara-vault')->group(function () {
    Route::get('credentials', CredentialIndex::class)
        ->middleware(PermissionMiddleware::using('vault.credential.view'))
        ->name('nawasara-vault.credential.index');

    Route::get('access-log', AccessLogIndex::class)
        ->middleware(PermissionMiddleware::using('vault.log.view'))
        ->name('nawasara-vault.access-log.index');
});
