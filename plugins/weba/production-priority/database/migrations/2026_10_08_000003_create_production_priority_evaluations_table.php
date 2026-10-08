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
        Schema::create('production_priority_evaluations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('trigger')->default('manual');
            $table->timestamp('evaluated_at');
            $table->json('criteria_snapshot');
            $table->json('warnings')->nullable();
            $table->unsignedInteger('order_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->unsignedSmallInteger('applied_top_n')->nullable();

            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();

            $table->foreignId('evaluated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('applied_by')
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
        Schema::dropIfExists('production_priority_evaluations');
    }
};
