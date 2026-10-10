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
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 60)->unique();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('address')->nullable();
            $table->string('detail_address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->date('join_date')->nullable();
            $table->string('status', 20)->default('Active');
            $table->string('gender', 20)->nullable();
            $table->date('dob')->nullable();
            $table->string('join_channel', 60)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('photo_id_path')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('salary')->default(0);
            $table->string('system', 20)->default('Monthly');
            $table->bigInteger('allowance_total')->default(0);
            $table->bigInteger('overtime_rate')->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('employee_salary_allowances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_salary_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('amount')->default(0);
            $table->timestamps();
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->string('slip_no', 60)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            $table->bigInteger('salary_rate')->default(0);
            $table->decimal('day_worked', 6, 2)->default(0);
            $table->bigInteger('allowance')->default(0);
            $table->bigInteger('overtime')->default(0);
            $table->bigInteger('deduction')->default(0);
            $table->bigInteger('total')->default(0);
            $table->string('status', 20)->default('Unpaid');
            $table->date('paid_date')->nullable();
            $table->timestamps();
        });

        Schema::create('payslip_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('qty', 12, 2)->default(1);
            $table->bigInteger('rate')->default(0);
            $table->bigInteger('amount')->default(0);
            $table->timestamps();
        });

        Schema::create('incentives', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('period', 20);
            $table->decimal('qty_complete', 15, 2)->default(0);
            $table->bigInteger('total_amount')->default(0);
            $table->string('status', 20)->default('Pending');
            $table->timestamps();
        });

        Schema::create('my_incentives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_name')->nullable();
            $table->foreignId('job_order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->date('date');
            $table->string('job_title')->nullable();
            $table->string('flow_name')->nullable();
            $table->string('process')->nullable();
            $table->bigInteger('incentive')->default(0);
            $table->string('unit', 30)->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->bigInteger('amount')->default(0);
            $table->string('status', 20)->default('Pending');
            $table->timestamps();
        });

        Schema::table('cash_advances', function (Blueprint $table) {
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('my_incentives');
        Schema::dropIfExists('incentives');
        Schema::dropIfExists('payslip_items');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('employee_salary_allowances');
        Schema::dropIfExists('employee_salaries');
        Schema::dropIfExists('employees');
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
