<?php

namespace Nawasara\Vault\Livewire\Credential\Section;

use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Attributes\Computed;
use Nawasara\Vault\Models\Credential;
use Nawasara\Vault\Facades\Vault;

class Table extends Component
{
    // Modal state
    public bool $showModal = false;
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
            $storedCount = Vault::storedCount($key);
            $totalFields = count($config['fields'] ?? []);

            $result[$key] = [
                'config' => $config,
                'stored' => $storedCount,
                'total' => $totalFields,
                'configured' => $storedCount >= $totalFields,
                'multi_instance' => $isMulti,
                'instances' => $instances,
            ];
        }

        return $result;
    }

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

            $this->fields[$key] = [
                'value' => $credential?->value ?? '',
                'config' => $fieldConfig,
                'has_value' => (bool) $credential,
            ];
        }

        $this->showModal = true;
    }

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
                'value' => '',
                'config' => $fieldConfig,
                'has_value' => false,
            ];
        }

        $this->showModal = true;
    }

    public function toggleReveal(string $key)
    {
        Gate::authorize('vault.credential.reveal');

        $this->revealed[$key] = ! ($this->revealed[$key] ?? false);
    }

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

        toaster_success('Credential berhasil disimpan');
        $this->showModal = false;
        unset($this->groups);
    }

    public function deleteInstance(string $group, string $instance)
    {
        Gate::authorize('vault.credential.manage');

        $credentials = Credential::where('group', $group)
            ->where('instance', $instance)
            ->get();

        foreach ($credentials as $credential) {
            Vault::delete($group, $credential->key, $instance);
        }

        toaster_success("Instance \"{$instance}\" berhasil dihapus");
        unset($this->groups);
    }

    public function render()
    {
        return view('nawasara-vault::livewire.pages.credential.section.table');
    }
}
