<?php
declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_types',function (Blueprint $table): void {
            $table->string('sku',80)->nullable()->index();
            $table->string('unit',20)->default('ea');
            $table->text('default_note')->nullable();
            $table->text('description')->nullable();
        });
        Schema::create('inventory_imports',function (Blueprint $table): void {
            $table->id();
            $table->char('content_hash',64)->unique();
            $table->unsignedBigInteger('row_count');
            $table->unsignedBigInteger('item_type_count');
            $table->unsignedBigInteger('asset_count');
            $table->unsignedBigInteger('created_by');
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Inventory imports and catalog metadata are retained to preserve asset history.');
    }
};
