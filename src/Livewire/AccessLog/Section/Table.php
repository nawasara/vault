<?php

namespace Nawasara\Vault\Livewire\AccessLog\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Nawasara\Vault\Models\AccessLog;

class Table extends Component
{
    use WithPagination;

    public string $search = '';
    public string $actionFilter = '';

    public function updatedSearch() { $this->resetPage(); }
    public function updatedActionFilter() { $this->resetPage(); }

    #[Computed]
    public function items()
    {
        return AccessLog::query()
            ->with(['credential', 'user'])
            ->search($this->search)
            ->when($this->actionFilter, fn ($q) => $q->where('action', $this->actionFilter))
            ->latest('created_at')
            ->paginate(20);
    }

    public function render()
    {
        return view('nawasara-vault::livewire.pages.access-log.section.table');
    }
}
