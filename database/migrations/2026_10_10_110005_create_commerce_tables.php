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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('image')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->bigInteger('price')->default(0);
            $table->string('spec', 500)->default('');
            $table->string('status', 20)->default('Active');
            $table->string('job_title')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('image')->nullable();
            $table->bigInteger('price')->default(0);
            $table->string('spec', 500)->default('');
            $table->string('status', 20)->default('Active');
            $table->date('added_at')->nullable();
            $table->date('expire_date')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'product_id', 'spec']);
        });

        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->string('no_quotation', 60)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->date('due_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 40)->default('Draft');
            $table->string('channel', 30)->default('online');
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->bigInteger('sub_total')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('shipping_cost')->default(0);
            $table->bigInteger('total')->default(0);
            $table->json('document')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('channel', 30)->default('website');
            $table->string('status', 40)->default('pending');
            $table->string('status_by')->nullable();
            $table->string('shipping_method', 30)->nullable();
            $table->json('shipping_address')->nullable();
            $table->json('contact')->nullable();
            $table->bigInteger('sub_total')->default(0);
            $table->bigInteger('delivery_fee')->default(0);
            $table->bigInteger('discount')->default(0);
            $table->bigInteger('tax')->default(0);
            $table->bigInteger('total')->default(0);
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('due')->default(0);
            $table->string('payment_status', 20)->default('Unpaid');
            $table->string('po_no', 60)->nullable();
            $table->text('notes')->nullable();
            $table->string('voucher_code')->nullable();
            $table->foreignId('source_quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->dateTime('ordered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['channel', 'status']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('spec', 500)->default('');
            $table->string('artwork')->nullable();
            $table->string('print_result')->nullable();
            $table->text('note')->nullable();
            $table->string('job_title')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 60);
            $table->text('note')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');
            $table->text('description')->nullable();
            $table->decimal('moq', 15, 2)->nullable();
            $table->decimal('order_qty', 15, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('amount')->default(0);
            $table->string('spec', 500)->default('');
            $table->string('category')->nullable();
            $table->string('artwork')->nullable();
            $table->string('print_result')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_no', 60)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('amount_due')->default(0);
            $table->string('status', 20)->default('Unpaid');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->string('direction', 10)->default('in');
            $table->string('ref_no', 60)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->bigInteger('amount')->default(0);
            $table->string('method', 40)->nullable();
            // FK bank_account_id ditambahkan saat tabel bank_accounts dibuat (Fase 4).
            $table->unsignedBigInteger('bank_account_id')->nullable()->index();
            $table->string('status', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tax_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rates');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });
        Schema::dropIfExists('orders');
        Schema::dropIfExists('quotations');
        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('cart_items');
    }
};
