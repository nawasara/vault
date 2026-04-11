<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Vault', 'url' => '#'], ['label' => 'Credentials']]" />
    </x-slot>

    <x-nawasara-ui::page.container>
        <x-nawasara-ui::page.title>Credential Management</x-nawasara-ui::page.title>
        @livewire('nawasara-vault.credential.section.table')
    </x-nawasara-ui::page.container>
</div>
