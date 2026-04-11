<?php

namespace Nawasara\Vault\Services;

use Nawasara\Vault\Models\Credential;
use Nawasara\Vault\Models\AccessLog;

class VaultManager
{
    public function get(string $group, string $key, ?string $instance = null): ?string
    {
        $credential = Credential::query()
            ->where('group', $group)
            ->where('key', $key)
            ->forInstance($instance)
            ->first();

        if (! $credential) {
            return null;
        }

        // Update last accessed
        $credential->update(['last_accessed_at' => now()]);

        // Log access
        if (config('nawasara-vault.log_reads', true)) {
            $this->log($credential, 'read');
        }

        return $credential->value;
    }

    public function set(string $group, string $key, string $value, ?string $instance = null, ?string $description = null): Credential
    {
        $credential = Credential::updateOrCreate(
            [
                'group' => $group,
                'key' => $key,
                'instance' => $instance,
            ],
            [
                'value' => $value,
                'description' => $description,
                'last_rotated_at' => now(),
                'rotated_by' => auth()->id(),
            ]
        );

        $this->log($credential, $credential->wasRecentlyCreated ? 'create' : 'update');

        return $credential;
    }

    public function has(string $group, string $key, ?string $instance = null): bool
    {
        return Credential::query()
            ->where('group', $group)
            ->where('key', $key)
            ->forInstance($instance)
            ->exists();
    }

    public function delete(string $group, string $key, ?string $instance = null): bool
    {
        $credential = Credential::query()
            ->where('group', $group)
            ->where('key', $key)
            ->forInstance($instance)
            ->first();

        if (! $credential) {
            return false;
        }

        $this->log($credential, 'delete');
        $credential->delete();

        return true;
    }

    public function group(string $group, ?string $instance = null): array
    {
        return Credential::query()
            ->where('group', $group)
            ->forInstance($instance)
            ->pluck('value', 'key')
            ->toArray();
    }

    public function instances(string $group): array
    {
        return Credential::query()
            ->where('group', $group)
            ->whereNotNull('instance')
            ->distinct()
            ->pluck('instance')
            ->toArray();
    }

    public function isConfigured(string $group, ?string $instance = null): bool
    {
        $fields = config("nawasara-vault.groups.{$group}.fields", []);

        if (empty($fields)) {
            return false;
        }

        $stored = Credential::query()
            ->where('group', $group)
            ->forInstance($instance)
            ->whereIn('key', array_keys($fields))
            ->count();

        return $stored >= count($fields);
    }

    public function storedCount(string $group, ?string $instance = null): int
    {
        return Credential::query()
            ->where('group', $group)
            ->forInstance($instance)
            ->count();
    }

    protected function log(Credential $credential, string $action): void
    {
        AccessLog::create([
            'credential_id' => $credential->id,
            'action' => $action,
            'accessor' => auth()->check() ? 'user' : 'system',
            'accessor_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }
}
