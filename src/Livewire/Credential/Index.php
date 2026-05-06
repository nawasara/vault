<?php

namespace Nawasara\Vault\Livewire\Credential;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Nawasara\Vault\Models\Credential;

class Index extends Component
{
    /**
     * Hero stats untuk Credentials page.
     *
     * Vault tidak pakai repository pattern (pre-existing), jadi query langsung
     * via Eloquent di sini — single-pass dengan selectRaw untuk efisiensi.
     *
     * KPI yang dipilih:
     * - Total: jumlah credential ter-store
     * - Groups: distinct group (jumlah service yang punya credential)
     * - Stale: belum di-rotate >90 hari (security debt)
     * - Never rotated: last_rotated_at NULL (highest urgency)
     */
    #[Computed]
    public function stats(): array
    {
        $row = Credential::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('COUNT(DISTINCT `group`) as group_count')
            ->selectRaw('SUM(CASE WHEN last_rotated_at IS NULL THEN 1 ELSE 0 END) as never_rotated')
            ->selectRaw('SUM(CASE WHEN last_rotated_at IS NOT NULL AND last_rotated_at < ? THEN 1 ELSE 0 END) as stale_count', [now()->subDays(90)])
            ->first();

        $stale = (int) ($row?->stale_count ?? 0);
        $never = (int) ($row?->never_rotated ?? 0);

        return [
            ['label' => 'Total Credentials', 'value' => number_format((int) ($row?->total ?? 0)), 'icon' => 'lucide-key-round', 'color' => 'primary'],
            ['label' => 'Service Groups', 'value' => number_format((int) ($row?->group_count ?? 0)), 'icon' => 'lucide-folder-tree', 'color' => 'info', 'description' => 'distinct service'],
            ['label' => 'Stale (>90 hari)', 'value' => number_format($stale), 'icon' => 'lucide-clock-alert', 'color' => $stale > 0 ? 'warning' : 'success', 'description' => $stale > 0 ? 'perlu di-rotate' : 'semua segar'],
            ['label' => 'Never Rotated', 'value' => number_format($never), 'icon' => 'lucide-shield-alert', 'color' => $never > 0 ? 'danger' : 'success', 'description' => $never > 0 ? 'rotate ASAP' : 'semua aman'],
        ];
    }

    public function render()
    {
        return view('nawasara-vault::livewire.pages.credential.index')
            ->layout('nawasara-ui::components.layouts.app');
    }
}
