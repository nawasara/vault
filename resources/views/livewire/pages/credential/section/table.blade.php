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
                @if ($isMulti)
                    {{-- Multi-instance: show instance list --}}
                    <div class="space-y-1.5 mb-3">
                        @foreach ($group['instances'] as $instance)
                            <div class="flex items-center justify-between py-1.5 px-3 rounded-lg bg-gray-50 dark:bg-neutral-700/50 text-sm">
                                <span class="text-gray-700 dark:text-neutral-300">{{ $instance }}</span>
                                <div class="flex items-center gap-2">
                                    <button wire:click="openGroup('{{ $groupKey }}', '{{ $instance }}')"
                                        class="text-blue-600 hover:underline text-xs">Edit</button>
                                    <button wire:click="deleteInstance('{{ $groupKey }}', '{{ $instance }}')"
                                        wire:confirm="Hapus semua credential untuk instance '{{ $instance }}'?"
                                        class="text-red-500 hover:underline text-xs">Hapus</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button wire:click="addInstance('{{ $groupKey }}')"
                        class="w-full py-2 text-sm text-center text-blue-600 hover:bg-blue-50 rounded-lg border border-dashed border-blue-300 dark:text-blue-400 dark:hover:bg-blue-900/20 dark:border-blue-800 transition-colors">
                        + Tambah Instance
                    </button>
                @else
                    <button wire:click="openGroup('{{ $groupKey }}')"
                        class="w-full py-2.5 text-sm font-medium text-center rounded-lg border transition-colors
                        {{ $configured
                            ? 'border-gray-200 text-gray-700 hover:bg-gray-50 dark:border-neutral-600 dark:text-neutral-300 dark:hover:bg-neutral-700'
                            : 'border-green-300 text-green-700 bg-green-50 hover:bg-green-100 dark:border-green-800 dark:text-green-400 dark:bg-green-900/20 dark:hover:bg-green-900/30' }}">
                        {{ $configured ? 'Edit Credential' : 'Setup Credential' }}
                    </button>
                @endif
            </div>
        @endforeach
    </div>

    {{-- Modal Edit Credential --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50" wire:click.self="$set('showModal', false)">
            <div class="bg-white dark:bg-neutral-800 rounded-xl shadow-xl w-full max-w-lg mx-4 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-neutral-700">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-neutral-200">
                        {{ config("nawasara-vault.groups.{$editingGroup}.label", $editingGroup) }}
                        @if ($editingInstance)
                            <span class="text-sm font-normal text-gray-500">— {{ $editingInstance }}</span>
                        @endif
                    </h3>
                </div>

                <form wire:submit="save" class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                    {{-- Instance name for multi-instance --}}
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
                            @if (($fieldConfig['type'] ?? 'text') === 'password')
                                <x-nawasara-ui::form.label :value="$fieldConfig['label']" />
                                <div class="relative" x-data="{ show: false }">
                                    <input
                                        :type="show ? 'text' : 'password'"
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

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showModal', false)"
                            class="py-2.5 px-4 text-sm font-medium rounded-lg border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-white">
                            Batal
                        </button>
                        <x-nawasara-ui::button type="submit" color="primary">Simpan</x-nawasara-ui::button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
