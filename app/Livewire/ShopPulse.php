<?php
declare(strict_types=1);
namespace App\Livewire;

use App\Middleware\Permissions;
use App\Models\Database as DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class ShopPulse extends Component
{
    #[Locked]
    public int $cursor = 0;

    #[Locked]
    public string $checkedAt = '';

    public function mount(): void
    {
        Permissions::require('shows.view');
        $this->cursor = $this->latest();
        $this->checkedAt = gmdate('H:i:s');
    }

    public function refresh(): void
    {
        Permissions::require('shows.view');
        $latest = $this->latest();
        if ($latest!==$this->cursor) {
            $this->cursor = $latest;
            $this->dispatch('shop-updated');
        }
        $this->checkedAt = gmdate('H:i:s');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        Permissions::require('shows.view');
        return view('livewire.shop-pulse');
    }

    private function latest(): int
    {
        return (int)DB::one('SELECT COALESCE(MAX(id),0) AS latest FROM audit_logs')['latest'];
    }
}
