<?php

use Illuminate\Support\Facades\Route;
use Nawasara\Vault\Livewire\Credential\Index as CredentialIndex;
use Nawasara\Vault\Livewire\AccessLog\Index as AccessLogIndex;

Route::middleware(['web', 'auth'])->prefix('nawasara-vault')->group(function () {
    Route::get('credentials', CredentialIndex::class)->name('nawasara-vault.credential.index');
    Route::get('access-log', AccessLogIndex::class)->name('nawasara-vault.access-log.index');
});
