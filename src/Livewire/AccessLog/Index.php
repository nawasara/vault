<?php

namespace Nawasara\Vault\Livewire\AccessLog;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-vault::livewire.pages.access-log.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
