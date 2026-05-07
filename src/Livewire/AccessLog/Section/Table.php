<?php

namespace Nawasara\Vault\Livewire\AccessLog\Section;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Computed;
use Nawasara\Ui\Livewire\Concerns\HasExport;
use Nawasara\Ui\Livewire\Concerns\HasTimeWindow;
use Nawasara\Vault\Models\AccessLog;

class Table extends Component
{
    use HasExport;
    use HasTimeWindow;
    use WithPagination;

    public string $search = '';

    /**
     * Multi-select action filter (read/create/update/delete).
     * Empty array == no filter.
     *
     * @var array<int, string>
     */
    public array $actionFilter = [];

    public function updatedSearch() { $this->resetPage(); }
    public function updatedActionFilter() { $this->resetPage(); }

    #[Computed]
    public function items()
    {
        return AccessLog::query()
            ->with(['credential', 'user'])
            ->tap(fn ($q) => $this->applyTimeWindow($q, 'created_at'))
            ->search($this->search)
            ->when(! empty($this->actionFilter), fn ($q) => $q->whereIn('action', $this->actionFilter))
            ->latest('created_at')
            ->paginate(20);
    }

    /**
     * Export filename base — timestamp + extension appended by HasExport.
     */
    protected function exportFilename(): string
    {
        return 'vault-access-log';
    }

    /**
     * Export FULL access log (capped) per spec. Vault audit trail can
     * grow large; the cap keeps xlsx generation bounded.
     */
    protected function exportData(): iterable
    {
        return AccessLog::query()
            ->with(['credential', 'user'])
            ->latest('created_at')
            ->limit(10000)
            ->get()
            ->map(fn (AccessLog $log) => [
                'ID' => $log->id,
                'Created' => optional($log->created_at)->format('Y-m-d H:i:s'),
                'Credential Group' => $log->credential?->group,
                'Credential Key' => $log->credential?->key,
                'Credential Instance' => $log->credential?->instance,
                'Action' => $log->action,
                'Accessor Type' => $log->accessor,
                'User Name' => $log->user?->name,
                'IP Address' => $log->ip_address,
            ]);
    }

    public function render()
    {
        return view('nawasara-vault::livewire.pages.access-log.section.table');
    }
}
