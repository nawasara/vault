<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Vault', 'url' => '#'], ['label' => 'Access Log']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Vault Access Log</x-nawasara-ui::page.title>
        @livewire('nawasara-vault.access-log.section.table')
    </x-nawasara-ui::page.container>
</div>
