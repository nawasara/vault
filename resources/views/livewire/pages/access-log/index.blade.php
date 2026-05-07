<div>
    <x-slot name="breadcrumb">
        <livewire:nawasara-ui.shared-components.breadcrumb
            :items="[['label' => 'Vault', 'url' => '#'], ['label' => 'Access Log']]" />
    </x-slot>

    {{-- Title + time-window hoisted into section component (which owns
         the reactive state). Index is a shell. --}}
    <x-nawasara-ui::page.container>
        @livewire('nawasara-vault.access-log.section.table')
    </x-nawasara-ui::page.container>
</div>
