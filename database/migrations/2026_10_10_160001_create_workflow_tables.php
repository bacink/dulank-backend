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
        Schema::create('flow_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->nullable()->unique();
            $table->string('name');
            $table->unsignedInteger('used_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('flow_names', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->nullable()->unique();
            $table->foreignId('flow_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->bigInteger('incentive')->default(0);
            $table->string('unit', 30)->nullable();
            $table->string('flow_type', 20)->default('Inhouse');
            $table->json('assignees')->nullable();
            $table->timestamps();
        });

        Schema::create('flow_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->nullable()->unique();
            $table->string('name');
            $table->text('information')->nullable();
            $table->timestamps();
        });

        Schema::create('work_flows', function (Blueprint $table) {
            $table->id();
            $table->string('no', 60)->unique();
            $table->date('date');
            $table->foreignId('flow_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->text('workflow_steps')->nullable();
            $table->json('steps')->nullable();
            $table->timestamps();
        });

        Schema::create('work_flow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_flow_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('template')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('job_orders', function (Blueprint $table) {
            $table->id();
            $table->string('no', 60)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('flow_name_id')->nullable()->constrained('flow_names')->nullOnDelete();
            $table->string('flow_type', 20)->nullable();
            $table->date('due_date')->nullable();
            $table->string('customer_name')->nullable();
            $table->string('product_name')->nullable();
            $table->string('job_title')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->string('priority', 20)->default('Medium');
            $table->string('status', 30)->default('Waiting');
            $table->string('workflow_type', 40)->nullable();
            $table->string('workflow_category', 40)->nullable();
            $table->string('sales_no', 60)->nullable();
            $table->date('sales_date')->nullable();
            $table->string('shipping')->nullable();
            $table->text('order_summary')->nullable();
            $table->string('assigned_to')->nullable();
            $table->json('input_specs')->nullable();
            $table->json('output_specs')->nullable();
            $table->timestamps();
        });

        Schema::create('job_order_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('job_branches', function (Blueprint $table) {
            $table->id();
            $table->string('no', 60)->unique();
            $table->foreignId('job_order_id')->constrained()->cascadeOnDelete();
            $table->string('order_no', 60)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->string('branch')->nullable();
            $table->string('customer')->nullable();
            $table->string('product')->nullable();
            $table->string('flow_name')->nullable();
            $table->string('priority', 20)->default('Medium');
            $table->string('status', 30)->default('Waiting');
            $table->string('job_title')->nullable();
            $table->text('description')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->date('date')->nullable();
            $table->date('date_finish')->nullable();
            $table->json('info_list')->nullable();
            $table->json('after_info_list')->nullable();
            $table->json('assignees')->nullable();
            $table->bigInteger('incentive_amount')->default(0);
            $table->string('incentive_unit', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('job_branch_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_branch_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('branch')->nullable();
            $table->string('customer')->nullable();
            $table->string('flow_name')->nullable();
            $table->date('date_finish')->nullable();
            $table->timestamps();
        });

        Schema::create('job_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('progress_code', 60)->nullable();
            $table->string('product')->nullable();
            $table->text('description')->nullable();
            $table->string('process')->nullable();
            $table->string('completed_by')->nullable();
            $table->dateTime('time')->nullable();
            $table->string('note')->nullable();
            $table->boolean('is_completed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_progress');
        Schema::dropIfExists('job_branch_histories');
        Schema::dropIfExists('job_branches');
        Schema::dropIfExists('job_order_steps');
        Schema::dropIfExists('job_orders');
        Schema::dropIfExists('work_flow_steps');
        Schema::dropIfExists('work_flows');
        Schema::dropIfExists('flow_templates');
        Schema::dropIfExists('flow_names');
        Schema::dropIfExists('flow_categories');
    }
};
