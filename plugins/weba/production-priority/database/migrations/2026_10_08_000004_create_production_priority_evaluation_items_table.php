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
        Schema::create('production_priority_evaluation_items', function (Blueprint $table) {
            $table->id();
            $table->json('raw_values');
            $table->json('normalized_values');
            $table->decimal('score', 10, 6);
            $table->unsignedInteger('rank');
            $table->string('previous_priority')->nullable();
            $table->boolean('applied')->default(false);

            $table->foreignId('evaluation_id')
                ->constrained('production_priority_evaluations', indexName: 'pp_eval_items_evaluation_foreign')
                ->cascadeOnDelete();

            $table->foreignId('manufacturing_order_id')
                ->constrained('manufacturing_orders', indexName: 'pp_eval_items_order_foreign')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique(['evaluation_id', 'manufacturing_order_id'], 'pp_eval_items_evaluation_order_unique');
            $table->index(['evaluation_id', 'rank'], 'pp_eval_items_evaluation_rank_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production_priority_evaluation_items');
    }
};
