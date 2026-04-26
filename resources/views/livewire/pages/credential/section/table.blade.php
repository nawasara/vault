<div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($this->groups as $groupKey => $group)
            @php
                $config = $group['config'];
                $isMulti = $group['multi_instance'];
                $configured = $group['configured'];
            @endphp

            <div class="border border-gray-200 dark:border-neutral-700 rounded-xl p-5 bg-white dark:bg-neutral-800 hover:shadow-md transition-shadow">
                {{-- Header --}}
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center size-10 rounded-lg {{ $configured ? 'bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-400 dark:bg-neutral-700 dark:text-neutral-500' }}">
                            <x-dynamic-component :component="$config['icon'] ?? 'lucide-key'" class="size-5" />
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800 dark:text-neutral-200">{{ $config['label'] }}</h3>
                            <p class="text-xs text-gray-500 dark:text-neutral-400">
                                {{ $group['stored'] }}/{{ $group['total'] }} field
                            </p>
                        </div>
                    </div>
                    @if ($configured)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                            Configured
                        </span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-50 text-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-400">
                            Belum lengkap
                        </span>
                    @endif
                </div>

                {{-- Actions --}}
                @php $hasTest = ! empty($config['test']); @endphp

                @if ($isMulti)
                    {{-- Multi-instance: show instance list --}}
                    <div class="space-y-1.5 mb-3">
                        @foreach ($group['instances'] as $instance)
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-gray-50 dark:bg-neutral-700/50 text-sm">
                                <span class="text-gray-700 dark:text-neutral-300">{{ $instance }}</span>
                                <x-nawasara-ui::dropdown-menu-action :id="$instance" :items="array_filter([
                                    ['type' => 'click', 'label' => 'Edit', 'wire:click' => 'openGroup(\'' . $groupKey . '\', \'' . $instance . '\')', 'modal' => 'vault-credential-form', 'icon' => 'lucide-pencil', 'permission' => 'vault.credential.view'],
                                    $hasTest ? ['type' => 'click', 'label' => 'Test Connection', 'wire:click' => 'testConnection(\'' . $groupKey . '\', \'' . $instance . '\')', 'icon' => 'lucide-plug', 'permission' => 'vault.credential.view'] : null,
                                    ['type' => 'click', 'label' => 'Hapus', 'wire:click' => 'deleteInstance(\'' . $groupKey . '\', \'' . $instance . '\')', 'icon' => 'lucide-trash-2', 'confirm' => 'Yakin ingin menghapus instance ini?', 'permission' => 'vault.credential.manage'],
                                ])" />
                            </div>
                        @endforeach
                    </div>
                    <button wire:click="addInstance('{{ $groupKey }}')"
                        @click="$dispatch('open-modal', {id: 'vault-credential-form', loading: true})"
                        class="w-full py-2 text-sm text-center text-blue-600 hover:bg-blue-50 rounded-lg border border-dashed border-blue-300 dark:text-blue-400 dark:hover:bg-blue-900/20 dark:border-blue-800 transition-colors">
                        + Tambah Instance
                    </button>
                @else
                    <div class="flex gap-2">
                        <button wire:click="openGroup('{{ $groupKey }}')"
                            @click="$dispatch('open-modal', {id: 'vault-credential-form', loading: true})"
                            class="flex-1 py-2.5 text-sm font-medium text-center rounded-lg border transition-colors
                            {{ $configured
                                ? 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-700'
                                : 'border-green-300 text-green-700 bg-green-50 hover:bg-green-100 dark:border-green-800 dark:text-green-400 dark:bg-green-900/20 dark:hover:bg-green-900/30' }}">
                            {{ $configured ? 'Edit Credential' : 'Setup Credential' }}
                        </button>
                        @if ($hasTest && $configured)
                            <button wire:click="testConnection('{{ $groupKey }}')" type="button"
                                title="Test Connection"
                                class="px-3 py-2.5 text-sm rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 dark:border-neutral-600 dark:text-neutral-400 dark:hover:bg-neutral-700 transition-colors">
                                <x-lucide-plug class="size-4" wire:loading.class="animate-pulse" wire:target="testConnection('{{ $groupKey }}')" />
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Modal Edit Credential --}}
    <x-nawasara-ui::modal id="vault-credential-form"
        :title="config('nawasara-vault.groups.'.$editingGroup.'.label', $editingGroup)"
        :subtitle="$editingInstance ? '— '.$editingInstance : null">
        <form wire:submit="save" id="vault-credential-form" class="space-y-4">
            @if ($isNewInstance)
                <div>
                    <x-nawasara-ui::form.input label="Nama Instance" placeholder="router-kantor-utama"
                        wire:model="editingInstance" useError errorVariable="editingInstance" />
                    <p class="text-xs text-gray-500 mt-1">Identifier unik untuk instance ini</p>
                </div>
                <hr class="dark:border-neutral-700">
            @endif

            @foreach ($fields as $key => $field)
                @php $fieldConfig = $field['config']; @endphp
                <div>
                    @if (($fieldConfig['type'] ?? 'text') === 'textarea')
                        <x-nawasara-ui::form.label :value="$fieldConfig['label']" />
                        <textarea
                            wire:model="fields.{{ $key }}.value"
                            placeholder="{{ $fieldConfig['placeholder'] ?? '' }}"
                            rows="{{ $fieldConfig['rows'] ?? 6 }}"
                            class="py-3 px-4 block w-full border border-gray-300 rounded-md text-sm font-mono transition-all duration-200 focus:border-transparent focus:ring-2 focus:ring-green-700/80 outline-none dark:bg-neutral-900 dark:border-gray-800 text-gray-900 dark:text-neutral-100"></textarea>
                        @if ($field['has_value'] && empty($field['value']))
                            <p class="text-xs text-gray-400 mt-1">Sudah tersimpan. Kosongkan jika tidak ingin mengubah.</p>
                        @endif
                    @elseif (($fieldConfig['type'] ?? 'text') === 'password')
                        <x-nawasara-ui::form.label :value="$fieldConfig['label']" />
                        <div class="relative" x-data="{ show: false }">
                            <input :type="show ? 'text' : 'password'"
                                wire:model="fields.{{ $key }}.value"
                                placeholder="{{ $fieldConfig['placeholder'] ?? '••••••••' }}"
                                class="py-3 px-4 pe-12 block w-full border border-gray-300 rounded-md text-sm transition-all duration-200 focus:border-transparent focus:ring-2 focus:ring-green-700/80 outline-none dark:bg-neutral-900 dark:border-gray-800 text-gray-900 dark:text-neutral-100" />
                            <button type="button" @click="show = !show"
                                class="absolute inset-y-0 end-0 flex items-center pe-3.5 text-gray-400 hover:text-gray-600 dark:hover:text-neutral-300">
                                <x-lucide-eye x-show="!show" class="size-4" />
                                <x-lucide-eye-off x-show="show" class="size-4" x-cloak />
                            </button>
                        </div>
                        @if ($field['has_value'] && empty($field['value']))
                            <p class="text-xs text-gray-400 mt-1">Sudah tersimpan. Kosongkan jika tidak ingin mengubah.</p>
                        @endif
                    @elseif (($fieldConfig['type'] ?? 'text') === 'select')
                        <x-nawasara-ui::form.label :value="$fieldConfig['label']" />
                        <x-nawasara-ui::form.select wire:model="fields.{{ $key }}.value" :placeholder="false">
                            @foreach ($fieldConfig['options'] ?? [] as $optVal => $optLabel)
                                <option value="{{ $optVal }}">{{ $optLabel }}</option>
                            @endforeach
                        </x-nawasara-ui::form.select>
                    @else
                        <x-nawasara-ui::form.input
                            :label="$fieldConfig['label']"
                            :placeholder="$fieldConfig['placeholder'] ?? ''"
                            wire:model="fields.{{ $key }}.value" />
                    @endif
                </div>
            @endforeach
        </form>

        <x-slot:footer>
            <x-nawasara-ui::button color="neutral" variant="outline" @click="$dispatch('close-modal', 'vault-credential-form')">Batal</x-nawasara-ui::button>
            <x-nawasara-ui::button type="submit" form="vault-credential-form" color="primary">Simpan</x-nawasara-ui::button>
        </x-slot:footer>
    </x-nawasara-ui::modal>
</div>
