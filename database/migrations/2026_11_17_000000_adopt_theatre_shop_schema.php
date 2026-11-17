<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the original migration bytes, checksums, triggers, and populated installations.
        \App\Services\Migrations::applyLegacy();
    }

    public function down(): void
    {
        throw new RuntimeException('Shop history cannot be rolled back destructively. Restore a verified backup instead.');
    }
};
