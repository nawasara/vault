<?php

namespace Nawasara\Vault\Livewire\Credential\Section;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
    /**
     * Status field di modal. Audit keamanan 15 Oktober 2026 (T7): `value`
     * di sini TIDAK PERNAH berisi nilai tersimpan, hanya apa yang diketik
     * pengguna (kosong = tidak diubah). Pengecualiannya field `select`, yang
     * isinya pilihan dari config, bukan rahasia. Nilai tersimpan hanya keluar
     * lewat toggleReveal(), satu field, tanpa singgah di properti publik.
     */
    public array $fields = [];

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
     * Membuka modal ubah kredensial.
     *
     * ⚠️ Audit keamanan 15 Oktober 2026 (T7): dulu metode ini memuat seluruh
     * nilai kredensial, termasuk kata sandi, ke `$this->fields`. Properti
     * publik ikut di setiap snapshot Livewire, jadi sandi sudah berada di
     * browser begitu modal terbuka, cukup dengan izin `view`, dan tanpa satu
     * baris pun di Access Log. Izin `reveal` tidak berarti apa-apa.
     *
     * Sekarang yang dimuat hanya STATUS (`has_value`), bukan nilainya. Field
     * yang sudah tersimpan tampil tersamar; nilainya diminta satu per satu
     * lewat toggleReveal(). Tidak bisa memilah berdasarkan tipe field:
     * `client_secret`, `api_token`, `bot_token` di config bertipe `text`.
     *
     * Sudo tetap dipasang: membuka modal adalah pintu ke formulir ubah, dan
     * mengubah kredensial dapat mengalihkan sambungan (lihat save()).
     */
    #[RequiresSudo(reason: 'membuka kredensial tersimpan')]
    public function openGroup(string $group, ?string $instance = null)
    {
        Gate::authorize('vault.credential.view');

        $this->editingGroup = $group;
        $this->editingInstance = $instance ?? '';
        $this->isNewInstance = false;

        $config = config("nawasara-vault.groups.{$group}", []);
        $this->fields = [];

        foreach ($config['fields'] ?? [] as $key => $fieldConfig) {
            $credential = Credential::where('group', $group)
                ->where('key', $key)
                ->forInstance($instance)
                ->first();

            $isSelect = ($fieldConfig['type'] ?? 'text') === 'select';

            // Select: nilai tersimpan dimuat karena isinya salah satu opsi
            // config (bukan rahasia) dan dropdown harus menampilkan pilihan
            // yang benar. Bila belum tersimpan, opsi pertama dipakai supaya
            // wire:model sejalan dengan yang tampil di browser. `original`
            // dipakai save() untuk tahu apakah select itu diubah.
            // Field lain SELALU mulai kosong.
            $stored = $isSelect && $credential ? (string) $credential->value : null;

            $this->fields[$key] = [
                'value' => $isSelect ? ($stored ?? $this->defaultValueFor($fieldConfig)) : '',
                'config' => $fieldConfig,
                'has_value' => (bool) $credential,
                'original' => $stored,
            ];
        }

        $this->clearRevealedInBrowser();
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

        $config = config("nawasara-vault.groups.{$group}", []);
        $this->fields = [];

        foreach ($config['fields'] ?? [] as $key => $fieldConfig) {
            $this->fields[$key] = [
                'value' => $this->defaultValueFor($fieldConfig),
                'config' => $fieldConfig,
                'has_value' => false,
                'original' => null,
            ];
        }

        $this->clearRevealedInBrowser();
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
     * Menampilkan SATU nilai tersimpan kepada pengguna.
     *
     * ⚠️ Sudo pada aksi MEMBACA, sengaja, dan bukan kelebihan kehati-hatian.
     * Begitu sebuah kata sandi tergambar di layar, ia dapat dibaca, difoto,
     * atau disalin, dan tidak ada cara menariknya kembali.
     *
     * Audit keamanan 15 Oktober 2026 (T7): inilah satu-satunya jalan nilai
     * tersimpan keluar ke browser. Nilainya dikirim lewat $this->js() sebagai
     * event browser, yang menumpang di bagian `effects` respons Livewire dan
     * TIDAK masuk snapshot: ia tidak ikut bolak-balik di request berikutnya
     * dan tidak terbaca dari properti komponen. Setiap pembukaan dicatat di
     * Access Log sebagai aksi `reveal` beserta user-nya.
     *
     * Namanya tetap toggleReveal demi kompatibilitas; menyembunyikan kembali
     * cukup di sisi Alpine dan tidak perlu ke server.
     */
    #[RequiresSudo(reason: 'menampilkan kredensial')]
    public function toggleReveal(string $key): void
    {
        Gate::authorize('vault.credential.view');
        Gate::authorize('vault.credential.reveal');

        $group = $this->editingGroup;

        // Hanya field yang memang dideklarasikan untuk grup ini.
        if ($group === '' || ! array_key_exists($key, config("nawasara-vault.groups.{$group}.fields", []))) {
            return;
        }

        $value = Vault::reveal($group, $key, $this->editingInstance ?: null);

        if ($value === null) {
            $this->toast('error', 'Nilai belum tersimpan');

            return;
        }

        $this->js(sprintf(
            'window.dispatchEvent(new CustomEvent("vault-revealed", { detail: %s }));',
            json_encode(
                ['key' => $key, 'value' => $value],
                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE,
            ),
        ));
    }

    /**
     * Hapus nilai yang sedang terbuka di browser saat modal dibuka ulang,
     * supaya nilai grup sebelumnya tidak tertinggal di state Alpine.
     */
    protected function clearRevealedInBrowser(): void
    {
        $this->js('window.dispatchEvent(new CustomEvent("vault-revealed-clear"));');
    }

    /**
     * Field mana yang benar-benar harus ditulis saat menyimpan.
     *
     * Kosong = tidak diubah: field tidak pernah diisi nilai tersimpan, jadi
     * kosong berarti pengguna tidak mengetik apa pun. Select ditulis hanya
     * bila berbeda dari nilai tersimpannya; tanpa itu setiap simpan menulis
     * ulang semua select dan memenuhi Access Log dengan `update` palsu.
     * Dipisah sebagai metode statis murni supaya dapat diuji tanpa DB.
     *
     * @return array<string, string> key => nilai baru
     */
    public static function fieldsToWrite(array $fields): array
    {
        $write = [];

        foreach ($fields as $key => $field) {
            $value = is_array($field) ? ($field['value'] ?? '') : '';

            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }

            $value = (string) $value;
            $original = $field['original'] ?? null;

            if ($original !== null && $value === (string) $original) {
                continue;
            }

            $write[$key] = $value;
        }

        return $write;
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

        // Hanya key yang dideklarasikan config grup ini; `fields` adalah
        // properti publik dan isinya dapat dikarang dari browser.
        $allowed = config("nawasara-vault.groups.{$group}.fields", []);

        foreach (self::fieldsToWrite($this->fields) as $key => $value) {
            if (array_key_exists($key, $allowed)) {
                Vault::set($group, $key, $value, $instance);
            }
        }

        // Kosongkan isian supaya sandi yang baru diketik tidak tinggal di
        // snapshot sesudah modal ditutup (audit keamanan 15 Oktober 2026, T7).
        $this->fields = [];

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

            $message = is_string($result['message'] ?? null) ? $result['message'] : null;

            if (($result['success'] ?? false) === true) {
                $this->toast('success', $message ?? 'Koneksi berhasil');
            } else {
                // Pesan dari handler tetap ditampilkan karena kontrak test
                // connection memang memuat alasan gagal untuk admin, tetapi
                // dipotong; versi utuhnya ke log.
                Log::warning('Vault test connection gagal', [
                    'group' => $group,
                    'instance' => $instance,
                    'message' => $message,
                ]);
                $this->toast('error', $message !== null ? Str::limit($message, 200) : 'Koneksi gagal');
            }
        } catch (\Throwable $e) {
            // Audit keamanan 15 Oktober 2026 (R8): pesan exception mentah
            // dapat memuat URL internal, path berkas, atau potongan balasan
            // server target. Pengguna cukup tahu bahwa gagal; detailnya ke log.
            Log::error('Vault test connection melempar exception', [
                'group' => $group,
                'instance' => $instance,
                'exception' => $e,
            ]);
            $this->toast('error', 'Test connection gagal karena galat tak terduga. Detail tercatat di log aplikasi.');
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
