<?php

namespace Nawasara\Vault\Services;

use Illuminate\Support\Facades\Cache;
use Nawasara\Vault\Models\Credential;
use Nawasara\Vault\Models\AccessLog;

class VaultManager
{
    /**
     * Baca kredensial, dengan cache berumur pendek.
     *
     * ⚠️ Sebelum 21 September 2026 metode ini menembak basis data TIGA KALI
     * pada setiap panggilan: SELECT kredensial, UPDATE `last_accessed_at`, dan
     * INSERT ke access log. Tidak ada cache sama sekali.
     *
     * Akibatnya terukur di produksi: 53 baris log per menit, artinya ~159
     * query per menit hanya untuk membaca kredensial yang isinya tidak
     * berubah. `nawasara_vault_access_log` tumbuh menjadi 5,9 juta baris
     * (~90 ribu per hari) dan `count()` atasnya butuh satu detik penuh.
     *
     * Penyebabnya bukan satu pemanggil yang nakal, melainkan bentuk
     * pemakaiannya: sebuah klien memanggil Vault sekali per FIELD
     * (`host`, `port`, `username`, `password` = empat panggilan), dan sync
     * berjalan tiap beberapa menit untuk banyak target. `database-monitor`
     * sendiri menghasilkan 56.743 pembacaan per field per tujuh hari.
     *
     * Cache-nya sengaja PENDEK (bawaan 60 detik). Kredensial yang dirotasi
     * admin harus segera berlaku; satu menit basi dapat diterima, satu jam
     * tidak. `set()` dan `forget()` juga membuang cache-nya, jadi rotasi lewat
     * panel berlaku seketika.
     */
    public function get(string $group, string $key, ?string $instance = null): ?string
    {
        $ttl = (int) config('nawasara-vault.cache_ttl', 60);

        if ($ttl <= 0) {
            return $this->fetch($group, $key, $instance);
        }

        return Cache::remember(
            $this->cacheKey($group, $key, $instance),
            $ttl,
            fn () => $this->fetch($group, $key, $instance),
        );
    }

    /**
     * Ambil dari basis data, catat aksesnya, tanpa menyentuh cache.
     */
    protected function fetch(string $group, string $key, ?string $instance = null): ?string
    {
        $credential = Credential::query()
            ->where('group', $group)
            ->where('key', $key)
            ->forInstance($instance)
            ->first();

        if (! $credential) {
            return null;
        }

        // Update last accessed (query builder — skip model events to avoid flooding activity log)
        Credential::where('id', $credential->id)->update(['last_accessed_at' => now()]);

        // Log access to vault's own access log (not Spatie activity log)
        if (config('nawasara-vault.log_reads', true)) {
            $this->log($credential, 'read');
        }

        return $credential->value;
    }

    protected function cacheKey(string $group, string $key, ?string $instance): string
    {
        return 'nawasara-vault:'.$group.':'.$key.':'.($instance ?? '-');
    }

    /**
     * Buang cache satu kredensial. Dipanggil setiap kali nilainya berubah,
     * supaya rotasi berlaku seketika alih-alih menunggu TTL habis.
     */
    public function forget(string $group, string $key, ?string $instance = null): void
    {
        Cache::forget($this->cacheKey($group, $key, $instance));
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

        // Nilai berubah: cache harus dibuang sekarang, bukan saat TTL habis.
        $this->forget($group, $key, $instance);

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

        // Tanpa ini, kredensial yang sudah dihapus masih terbaca dari cache
        // sampai TTL habis, dan itu jauh lebih membingungkan daripada nilai
        // yang sekadar basi.
        $this->forget($group, $key, $instance);

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

        // Required = field tanpa flag 'optional' => true. Optional fields
        // tidak dihitung untuk status "lengkap".
        $requiredKeys = array_keys(array_filter(
            $fields,
            fn ($f) => empty($f['optional']),
        ));

        if (empty($requiredKeys)) {
            return true;
        }

        $stored = Credential::query()
            ->where('group', $group)
            ->forInstance($instance)
            ->whereIn('key', $requiredKeys)
            ->count();

        return $stored >= count($requiredKeys);
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
