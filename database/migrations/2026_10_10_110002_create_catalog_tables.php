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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 40)->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('image_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('sub_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 40)->nullable()->unique();
            $table->string('description')->nullable();
            $table->unsignedInteger('item_used')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_name', 20)->nullable();
            $table->unsignedInteger('item_used')->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('taggables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');

            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->bigInteger('price')->default(0);
            $table->bigInteger('price_from')->nullable();
            $table->bigInteger('price_to')->nullable();
            $table->string('price_type', 20)->default('Fixed');
            $table->string('selling_type', 40)->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('min_order_qty', 15, 2)->nullable();
            $table->decimal('quantity_alert', 15, 2)->nullable();
            $table->bigInteger('min_price')->nullable();
            $table->bigInteger('druck_price')->nullable();
            $table->decimal('min_length', 10, 2)->nullable();
            $table->decimal('min_width', 10, 2)->nullable();
            $table->string('discount_type', 20)->nullable();
            $table->bigInteger('discount_value')->default(0);
            $table->string('tax_type', 40)->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('variation');
            $table->string('value');
            $table->decimal('quantity', 15, 2)->default(0);
            $table->bigInteger('price')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('group_name');
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('product_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40)->nullable();
            $table->string('process_name');
            $table->string('image_path')->nullable();
            $table->timestamps();
        });

        Schema::create('workshop_services', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category', 60)->nullable();
            $table->json('specification')->nullable();
            $table->bigInteger('price')->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshop_services');
        Schema::dropIfExists('product_processes');
        Schema::dropIfExists('product_specs');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('products');
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('units');
        Schema::dropIfExists('sub_categories');
        Schema::dropIfExists('categories');
    }
};
