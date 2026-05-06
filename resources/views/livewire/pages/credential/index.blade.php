<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Vault', 'url' => '#'], ['label' => 'Credentials']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Credential Management</x-nawasara-ui::page.title>

        {{-- Hero stats — security posture KPI:
             "Stale" dan "Never Rotated" adalah security debt indicators.
             Color escalation: success (semua OK) → warning (perlu rotate) →
             danger (urgent action). --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            @foreach ($this->stats as $stat)
                <x-nawasara-ui::stat-card
                    :label="$stat['label']"
                    :value="$stat['value']"
                    :icon="$stat['icon']"
                    :color="$stat['color']"
                    :description="$stat['description'] ?? null"
                    accent />
            @endforeach
        </div>

        @livewire('nawasara-vault.credential.section.table')
    </x-nawasara-ui::page.container>
</div>
