<?php

namespace Nawasara\Vault\Livewire\Credential;

use Livewire\Component;

class Index extends Component
{
    public function render()
    {
        return view('nawasara-vault::livewire.pages.credential.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
