<div>
    @php
        $actionOptions = ['read' => 'Read', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete'];
    @endphp

    {{-- Page header — title left, time-window right. Vault access log
         is event-shaped; default 7d keeps the initial query bounded. --}}
    <x-nawasara-ui::page-header
        title="Vault Access Log"
        description="Audit trail setiap kali credential di-decrypt / dimodifikasi. Termasuk who, when, where (IP)."
        :count="$this->items->total().' entries'">
        <x-nawasara-ui::time-window :window="$window" :from="$from" :to="$to" />
    </x-nawasara-ui::page-header>

    {{-- Toolbar — Action filter (multi-select) + search + export. --}}
    <div class="space-y-2 mb-4">
        <div class="flex flex-col md:flex-row md:flex-nowrap md:items-center gap-2">
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <x-nawasara-ui::filter-panel
                    label="Filter"
                    :state="['actionFilter' => $actionFilter]"
                    :multiple="['actionFilter']"
                    :labels="['actionFilter' => $actionOptions]"
                    :dimensions="['actionFilter' => 'Action']">
                    <x-nawasara-ui::filter-group label="Action" model="actionFilter" :items="$actionOptions" icon="lucide-zap" />
                </x-nawasara-ui::filter-panel>
            </div>

            <div class="relative w-full md:flex-1 md:min-w-0">
                <div class="absolute inset-y-0 start-0 flex items-center pointer-events-none ps-3.5">
                    <x-lucide-search class="shrink-0 size-4 text-gray-400 dark:text-neutral-500" />
                </div>
                <input type="text" wire:model.live.debounce.300ms="search"
                    placeholder="Cari group, key, atau IP..."
                    class="h-10 ps-10 pe-4 block w-full border border-gray-200 rounded-lg text-sm focus:border-emerald-600 focus:ring-emerald-600 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 dark:focus:ring-neutral-600" />
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <x-nawasara-ui::export-button
                    action="export"
                    tooltip="Ekspor access log (max 10rb baris)" />
            </div>
        </div>

        <div wire:ignore data-filter-chips></div>

        @if ($search)
            <div class="flex flex-wrap items-center gap-2">
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            </div>
        @endif
    </div>

    {{-- No stickyLast: read-only audit log, no action column. --}}
    <x-nawasara-ui::table :headers="['#', 'Credential', 'Action', 'Accessor', 'IP Address', 'Waktu']">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr wire:key="vault-log-{{ $item->id }}">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $item->id }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        <span class="font-medium">{{ $item->credential?->group }}</span>
                        <span class="text-gray-400">.{{ $item->credential?->key }}</span>
                        @if ($item->credential?->instance)
                            <span class="text-xs text-blue-500">[{{ $item->credential->instance }}]</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        @php
                            // Vault audit action color tokens. read = neutral
                            // (passive view); create = success; update = blue
                            // (informational); delete = danger.
                            $actionColor = match($item->action) {
                                'create' => 'success',
                                'update' => 'blue',
                                'delete' => 'danger',
                                default => 'neutral',
                            };
                        @endphp
                        <x-nawasara-ui::badge :color="$actionColor">
                            {{ ucfirst($item->action) }}
                        </x-nawasara-ui::badge>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        @if ($item->accessor === 'user')
                            {{ $item->user?->name ?? 'User #'.$item->accessor_id }}
                        @else
                            <span class="text-gray-400">System</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-neutral-400">
                        {{ $item->ip_address ?? '-' }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 dark:text-neutral-200">
                        {{ $item->created_at->format('d M Y H:i:s') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6">
                        @if ($search || ! empty($actionFilter) || $window !== '7d' || $from || $to)
                            <x-nawasara-ui::empty-state
                                icon="lucide-search-x"
                                title="Tidak ada log yang cocok"
                                description="Coba ubah periode/filter atau hapus search keyword."
                                variant="filter"
                                inline />
                        @else
                            <x-nawasara-ui::empty-state
                                icon="lucide-file-key"
                                title="Belum ada access log 7 hari terakhir"
                                description="Pilih periode lebih panjang atau Custom untuk melihat data lebih lama."
                                inline />
                        @endif
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
