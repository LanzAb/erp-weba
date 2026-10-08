<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('production_priority_order_attributes', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('urgency_level')->default(3);
            $table->decimal('order_value', 15, 2)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('manufacturing_order_id')
                ->unique('pp_order_attributes_order_unique')
                ->constrained('manufacturing_orders', indexName: 'pp_order_attributes_order_foreign')
                ->cascadeOnDelete();

            $table->foreignId('creator_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_priority_order_attributes');
    }
};
