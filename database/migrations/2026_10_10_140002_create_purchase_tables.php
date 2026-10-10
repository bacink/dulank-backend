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
        Schema::create('purchase_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('purchase_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 60)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('merk')->nullable();
            $table->bigInteger('price')->default(0);
            $table->string('unit', 30)->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('no_purchase', 60)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('status', 30)->default('Pending');
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('due')->default(0);
            $table->string('payment_status', 20)->default('Unpaid');
            $table->bigInteger('shipping_cost')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('qty', 15, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('total')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('no_po', 60)->unique();
            $table->date('date');
            $table->string('created_by_name')->nullable();
            $table->string('no_purchase', 60)->nullable()->index();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->string('po_status', 20)->default('Draft');
            $table->string('goods_status', 20)->default('Pending');
            $table->date('goods_date')->nullable();
            $table->string('goods_by')->nullable();
            $table->string('term_of_payment')->nullable();
            $table->date('delivery_date')->nullable();
            $table->string('delivery_address')->nullable();
            $table->string('vendor_reff')->nullable();
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('qty', 15, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('total')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('no_pr', 60)->unique();
            $table->string('no_purchase', 60)->nullable()->index();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('due')->default(0);
            $table->string('status', 30)->default('Pending');
            $table->string('status_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('return_qty', 15, 2)->default(0);
            $table->string('unit', 30)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('amount')->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_lines');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchase_categories');
    }
};
