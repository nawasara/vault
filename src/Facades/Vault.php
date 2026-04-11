<?php

namespace Nawasara\Vault\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string|null get(string $group, string $key, ?string $instance = null)
 * @method static \Nawasara\Vault\Models\Credential set(string $group, string $key, string $value, ?string $instance = null, ?string $description = null)
 * @method static bool has(string $group, string $key, ?string $instance = null)
 * @method static bool delete(string $group, string $key, ?string $instance = null)
 * @method static array group(string $group, ?string $instance = null)
 * @method static array instances(string $group)
 * @method static bool isConfigured(string $group, ?string $instance = null)
 * @method static int storedCount(string $group, ?string $instance = null)
 *
 * @see \Nawasara\Vault\Services\VaultManager
 */
class Vault extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'nawasara.vault';
    }
}
