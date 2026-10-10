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
        Schema::create('held_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer')->nullable();
            $table->json('items')->nullable();
            $table->bigInteger('sub_total')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('delivery_fee')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('method', 40)->nullable();
            $table->string('delivery', 30)->nullable();
            $table->text('note')->nullable();
            $table->dateTime('held_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('held_orders');
    }
};
