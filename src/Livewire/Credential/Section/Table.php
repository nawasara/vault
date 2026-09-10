<?php

namespace Nawasara\Vault\Livewire\Credential\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\AuthPrimitives\Attributes\RequiresSudo;
use Nawasara\AuthPrimitives\Traits\WithSudo;
use Nawasara\Vault\Models\Credential;
use Nawasara\Vault\Facades\Vault;

/**
 * Brankas kredensial — SETIAP aksi di sini digerbang sudo, bukan hanya yang
 * merusak.
 *
 * Di halaman lain, gerbang sudo dipasang pada aksi destruktif saja: membaca
 * daftar aman, menghapus tidak. Di sini pembacaan justru yang paling berbahaya.
 * Satu klik "tampilkan" mengeluarkan kata sandi dalam bentuk yang dapat dibaca
 * dan disalin, dan sesudah itu ia berada di luar jangkauan sistem ini
 * selamanya — tidak ada yang bisa ditarik kembali.
 *
 * Isi brankas ini termasuk kredensial yang menguasai seluruh infrastruktur:
 * root@pam Proxmox, admin Keycloak, pangkalan data produksi. Sesi yang
 * ditinggalkan terbuka di komputer bersama sudah cukup.
 *
 * `vault_access_log` mencatat siapa membuka apa, tetapi catatan hanya berguna
 * SETELAH kejadian. Sudo yang menahannya lebih dulu.
 */
class Table extends Component
{
    use WithSudo;

    // Modal state
    public string $editingGroup = '';
    public string $editingInstance = '';
    public bool $isNewInstance = false;
    public array $fields = [];

    // Reveal state
    public array $revealed = [];

    #[Computed]
    public function groups()
    {
        $groups = config('nawasara-vault.groups', []);
        $result = [];

        foreach ($groups as $key => $config) {
            $isMulti = $config['multi_instance'] ?? false;
            $instances = $isMulti ? Vault::instances($key) : [null];
            $totalFields = count($config['fields'] ?? []);

            if ($isMulti) {
                // Multi-instance: configured jika minimal 1 instance lengkap.
                $stored = collect($instances)->sum(fn ($inst) => Vault::storedCount($key, $inst));
                $configured = collect($instances)->contains(fn ($inst) => Vault::isConfigured($key, $inst));
            } else {
                $stored = Vault::storedCount($key);
                $configured = Vault::isConfigured($key);
            }

            $result[$key] = [
                'config' => $config,
                'stored' => $stored,
                'total' => $totalFields,
                'configured' => $configured,
                'multi_instance' => $isMulti,
                'instances' => $instances,
            ];
        }

        return $result;
    }

    /**
     * ⚠️ INILAH gerbang yang sebenarnya, bukan toggleReveal().
     *
     * Metode ini memuat nilai kredensial — termasuk kata sandi — ke
     * `$this->fields`, dan blade mengikatnya lewat `wire:model`. Begitu modal
     * terbuka, sandi itu SUDAH berada di snapshot Livewire di browser: terbaca
     * dari devtools, dari respons jaringan, dari mana pun, tanpa menekan
     * tombol mata sama sekali.
     *
     * Tombol mata di blade murni Alpine (`show ? 'text' : 'password'`) dan
     * tidak memanggil toggleReveal(). Menggerbang toggleReveal() saja berarti
     * menjaga pintu yang tidak dilewati siapa pun — tampak aman di kode, tidak
     * menahan apa pun pada kenyataannya.
     */
    #[RequiresSudo(reason: 'membuka kredensial tersimpan')]
    public function openGroup(string $group, ?string $instance = null)
    {
        Gate::authorize('vault.credential.view');

        $this->editingGroup = $group;
        $this->editingInstance = $instance ?? '';
        $this->isNewInstance = false;
        $this->revealed = [];

        $config = config("nawasara-vault.groups.{$group}", []);
        $this->fields = [];

        foreach ($config['fields'] ?? [] as $key => $fieldConfig) {
            $credential = Credential::where('group', $group)
                ->where('key', $key)
                ->forInstance($instance)
                ->first();

            // Pre-fill select fields dengan first option supaya Livewire wire:model
            // align dengan apa yang browser tampilkan secara visual. Tanpa ini,
            // user yang tidak menyentuh dropdown akan submit empty value dan field
            // di-skip saat save (lihat save() ! empty check).
            $this->fields[$key] = [
                'value' => $credential?->value ?? $this->defaultValueFor($fieldConfig),
                'config' => $fieldConfig,
                'has_value' => (bool) $credential,
            ];
        }

        $this->dispatch('modal-open:vault-credential-form');
    }

    /**
     * Instance baru — digerbang di sini, bukan hanya di save().
     *
     * Menahannya di depan berarti orang tidak mengisi seluruh formulir lebih
     * dulu untuk kemudian ditolak saat menyimpan; dan bila sesinya memang tidak
     * berhak, tak ada gunanya membiarkan formulirnya terbuka.
     */
    #[RequiresSudo(reason: 'menambah instance kredensial')]
    public function addInstance(string $group)
    {
        Gate::authorize('vault.credential.manage');

        $this->editingGroup = $group;
        $this->editingInstance = '';
        $this->isNewInstance = true;
        $this->revealed = [];

        $config = config("nawasara-vault.groups.{$group}", []);
        $this->fields = [];

        foreach ($config['fields'] ?? [] as $key => $fieldConfig) {
            $this->fields[$key] = [
                'value' => $this->defaultValueFor($fieldConfig),
                'config' => $fieldConfig,
                'has_value' => false,
            ];
        }

        $this->dispatch('modal-open:vault-credential-form');
    }

    /**
     * Pre-fill value untuk field baru. Buat select dengan options, pakai
     * key pertama supaya Livewire wire:model align dengan option yang
     * browser tampilkan secara visual. Field text/password biarkan empty.
     */
    protected function defaultValueFor(array $fieldConfig): string
    {
        if (($fieldConfig['type'] ?? 'text') === 'select') {
            $options = $fieldConfig['options'] ?? [];
            return (string) (array_key_first($options) ?? '');
        }
        return '';
    }

    /**
     * ⚠️ Sudo pada aksi MEMBACA — sengaja, dan bukan kelebihan kehati-hatian.
     *
     * Begitu sebuah kata sandi tergambar di layar, ia dapat dibaca, difoto,
     * atau disalin, dan tidak ada cara menariknya kembali. Berbeda dari
     * penghapusan yang masih dapat dipulihkan dari cadangan, kebocoran di sini
     * bersifat sekali dan selamanya.
     */
    #[RequiresSudo(reason: 'menampilkan kredensial')]
    public function toggleReveal(string $key)
    {
        Gate::authorize('vault.credential.reveal');

        $this->revealed[$key] = ! ($this->revealed[$key] ?? false);
    }

    /**
     * Menyimpan kredensial — baik baru maupun perubahan.
     *
     * Digerbang karena mengubah kredensial dapat MENGALIHKAN sambungan:
     * mengganti host pangkalan data ke mesin milik penyerang membuat aplikasi
     * mengirimkan datanya sendiri ke sana, tanpa satu pun galat muncul.
     */
    #[RequiresSudo(reason: 'menyimpan kredensial')]
    public function save()
    {
        Gate::authorize('vault.credential.manage');

        $group = $this->editingGroup;
        $instance = $this->editingInstance ?: null;
        $isMulti = config("nawasara-vault.groups.{$group}.multi_instance", false);

        if ($isMulti && $this->isNewInstance) {
            $this->validate([
                'editingInstance' => 'required|max:100',
            ], [
                'editingInstance.required' => 'Nama instance wajib diisi',
            ]);
            $instance = $this->editingInstance;
        }

        foreach ($this->fields as $key => $field) {
            if (! empty($field['value'])) {
                Vault::set($group, $key, $field['value'], $instance);
            }
        }

        $this->toast('success', 'Credential berhasil disimpan');
        $this->dispatch('modal-close:vault-credential-form');
        unset($this->groups);
    }

    /**
     * Test connection for a group (optionally per-instance).
     * Calls the handler declared in config (e.g. WhmClient@testConnection).
     */
    public function testConnection(string $group, ?string $instance = null): void
    {
        Gate::authorize('vault.credential.view');

        $handler = config("nawasara-vault.groups.{$group}.test");

        if (! $handler) {
            $this->toast('error', 'Test connection belum tersedia untuk service ini');
            return;
        }

        try {
            $result = app()->call($handler, ['instance' => $instance]);

            if (($result['success'] ?? false) === true) {
                $this->toast('success', $result['message'] ?? 'Koneksi berhasil');
            } else {
                $this->toast('error', $result['message'] ?? 'Koneksi gagal');
            }
        } catch (\Throwable $e) {
            $this->toast('error', 'Error: '.$e->getMessage());
        }
    }

    /**
     * Show toast via browser event (bypass session flash — works in AJAX context).
     * Uses global window.Toast from nawasara-toaster.
     */
    protected function toast(string $type, string $message): void
    {
        $js = sprintf(
            'window.Toast && window.Toast[%s] && window.Toast[%s](%s);',
            json_encode($type),
            json_encode($type),
            json_encode($message)
        );
        $this->js($js);
    }

    /**
     * Menghapus seluruh kredensial satu instance.
     *
     * Layanan yang memakainya berhenti bekerja seketika, dan pemulihannya
     * menuntut kredensial aslinya ada di tempat lain — yang belum tentu benar.
     */
    #[RequiresSudo(reason: 'menghapus instance kredensial')]
    public function deleteInstance(string $group, string $instance)
    {
        Gate::authorize('vault.credential.manage');

        $credentials = Credential::where('group', $group)
            ->where('instance', $instance)
            ->get();

        foreach ($credentials as $credential) {
            Vault::delete($group, $credential->key, $instance);
        }

        $this->toast('success', "Instance \"{$instance}\" berhasil dihapus");
        unset($this->groups);
    }

    public function render()
    {
        return view('nawasara-vault::livewire.pages.credential.section.table');
    }
}
