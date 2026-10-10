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
        Schema::create('bank_account_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('bank_account_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_name');
            $table->string('bank_name')->nullable();
            $table->string('account_no', 60)->nullable();
            $table->bigInteger('opening_balance')->default(0);
            $table->string('ifsc', 60)->nullable();
            $table->string('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('bank_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('direction', 10);
            $table->bigInteger('amount')->default(0);
            $table->dateTime('occurred_at')->nullable();
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('money_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('no', 60)->unique();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('from_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->foreignId('to_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->bigInteger('amount')->default(0);
            $table->string('description')->nullable();
            $table->dateTime('occurred_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            // FK employees ditambahkan pada migrasi HRM (Fase 4B).
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('employee_name')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->bigInteger('total_cash')->default(0);
            $table->unsignedInteger('installment_count')->default(1);
            $table->string('period', 20)->default('Monthly');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_advance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->bigInteger('amount')->default(0);
            $table->string('reference_id', 60)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('income_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('no', 60)->unique();
            $table->string('name');
            $table->foreignId('income_category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->bigInteger('amount')->default(0);
            $table->string('payment_method', 40)->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('no_expense', 60)->unique();
            $table->date('date');
            $table->foreignId('expense_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('Unpaid');
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('paid')->default(0);
            $table->bigInteger('due')->default(0);
            $table->text('description')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('input_tax_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->nullable()->constrained()->nullOnDelete();
            $table->date('invoice_date');
            $table->string('faktur_no', 60)->nullable();
            $table->string('credited', 10)->default('No');
            $table->timestamps();
        });

        Schema::create('output_tax_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('etax_date');
            $table->string('etax_number', 60)->nullable();
            $table->string('tx_code', 60)->nullable();
            $table->string('status', 20)->default('Draft');
            $table->timestamps();
        });

        Schema::create('customer_account_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->bigInteger('amount')->default(0);
            $table->dateTime('occurred_at')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_account_entries');
        Schema::dropIfExists('output_tax_documents');
        Schema::dropIfExists('input_tax_documents');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('income_categories');
        Schema::dropIfExists('cash_advance_payments');
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('money_transfers');
        Schema::dropIfExists('bank_ledger_entries');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('bank_account_types');
    }
};
