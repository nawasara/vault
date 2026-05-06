<div>
    <x-nawasara-ui::filter-bar searchPlaceholder="Cari group, key, IP..." searchModel="search">
        <x-nawasara-ui::filter-dropdown label="Action" model="actionFilter"
            :items="['all' => 'Semua', 'read' => 'Read', 'create' => 'Create', 'update' => 'Update', 'delete' => 'Delete']" />

        <x-slot:chips>
            @if ($actionFilter)
                <x-nawasara-ui::filter-chip label="Action: {{ ucfirst($actionFilter) }}" model="actionFilter" />
            @endif
            @if ($search)
                <x-nawasara-ui::filter-chip label="Cari: {{ $search }}" model="search" />
            @endif
        </x-slot:chips>
    </x-nawasara-ui::filter-bar>

    <x-nawasara-ui::table :headers="['#', 'Credential', 'Action', 'Accessor', 'IP Address', 'Waktu']" title="Access Log">
        <x-slot:table>
            @forelse ($this->items as $item)
                <tr>
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
                            $actionClass = match($item->action) {
                                'read' => 'bg-gray-100 text-gray-600 dark:bg-neutral-700 dark:text-neutral-400',
                                'create' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-400',
                                'update' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
                                'delete' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                                default => 'bg-gray-100 text-gray-600',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $actionClass }}">
                            {{ ucfirst($item->action) }}
                        </span>
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
                        <x-nawasara-ui::empty-state
                            icon="lucide-file-key"
                            title="Belum ada access log"
                            description="Setiap kali credential diakses (read/decrypt), akan tercatat di sini untuk audit trail."
                            inline />
                    </td>
                </tr>
            @endforelse
        </x-slot:table>

        <x-slot:footer>
            {{ $this->items->links() }}
        </x-slot:footer>
    </x-nawasara-ui::table>
</div>
