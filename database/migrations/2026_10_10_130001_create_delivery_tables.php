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
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->id();
            $table->string('dn_no', 60)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('customer')->nullable();
            $table->string('no_sales', 60)->nullable()->index();
            $table->string('shipping_address')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->date('date_status')->nullable();
            $table->string('po', 60)->nullable();
            $table->string('shipping_by')->nullable();
            $table->string('reference')->nullable();
            $table->string('receive_by')->nullable();
            $table->string('security')->nullable();
            $table->string('driver')->nullable();
            $table->string('issued_by')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_note_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_note_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('qty', 15, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->string('packing_qty', 60)->nullable();
            $table->string('weight', 60)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_note_items');
        Schema::dropIfExists('delivery_notes');
    }
};
