<div class="shop-live-status" wire:poll.15s="refresh">
    <button type="button" class="btn btn-sm" wire:click="refresh" wire:loading.attr="disabled"
            title="Live updates check every 15 seconds. Last checked {{ $checkedAt }} UTC.">
        <span class="live-status-dot" wire:loading.remove aria-hidden="true"></span>
        <span class="spinner-border spinner-border-sm" wire:loading aria-hidden="true"></span>
        <span wire:loading.remove>Live</span>
        <span wire:loading>Updating</span>
    </button>
    <span wire:offline class="text-danger" role="status">Offline</span>
</div>
