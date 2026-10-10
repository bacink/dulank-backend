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
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->string('type', 20)->default('Fixed');
            $table->bigInteger('discount')->default(0);
            $table->integer('limit')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->date('valid_from')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('discount_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('customers')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discount_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->bigInteger('value')->default(0);
            $table->string('type', 20)->default('Fixed');
            $table->date('valid_from')->nullable();
            $table->date('valid_till')->nullable();
            $table->json('days')->nullable();
            $table->json('products')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 60)->unique();
            $table->string('type', 20)->default('Fixed');
            $table->bigInteger('discount')->default(0);
            $table->integer('limit')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('all_products')->default(true);
            $table->boolean('once_per_customer')->default(false);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name')->nullable();
            $table->string('user_email')->nullable();
            $table->date('date')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('title')->nullable();
            $table->text('review')->nullable();
            $table->string('status', 20)->default('Publish');
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->text('message');
            $table->date('date')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('discount_plans');
        Schema::dropIfExists('coupons');
    }
};
