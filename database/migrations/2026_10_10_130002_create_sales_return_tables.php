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
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no', 60)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('customer')->nullable();
            $table->string('payment_status', 20)->default('Unpaid');
            $table->date('payment_date')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->bigInteger('total')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('qty_order', 15, 2)->default(0);
            $table->decimal('qty_return', 15, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('return_amount')->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
    }
};
