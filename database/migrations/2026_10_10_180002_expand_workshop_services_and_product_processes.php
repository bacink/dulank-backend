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
        Schema::table('product_processes', function (Blueprint $table) {
            $table->string('status', 20)->default('Active')->after('image_path');
        });

        Schema::table('workshop_services', function (Blueprint $table) {
            $table->foreignId('store_id')->nullable()->after('id')->constrained('stores')->nullOnDelete();
            $table->string('printing_type', 40)->nullable()->after('category');
            $table->decimal('max_height', 10, 2)->nullable()->after('printing_type');
            $table->decimal('max_width', 10, 2)->nullable()->after('max_height');
            $table->string('size_unit', 10)->nullable()->after('max_width');
            $table->decimal('quantity_minimum', 15, 2)->nullable()->after('size_unit');
            $table->string('price_unit', 20)->nullable()->after('quantity_minimum');
            $table->bigInteger('druck_price')->nullable()->after('price_unit');
            $table->string('min_size', 60)->nullable()->after('druck_price');
            $table->string('max_size', 60)->nullable()->after('min_size');
            $table->bigInteger('price_per_cm')->nullable()->after('max_size');
            $table->bigInteger('minimum_price')->nullable()->after('price_per_cm');
            $table->bigInteger('standard_price')->nullable()->after('minimum_price');
            $table->bigInteger('standard_minimum')->nullable()->after('standard_price');
            $table->bigInteger('half_cut_price')->nullable()->after('standard_minimum');
            $table->bigInteger('half_cut_minimum')->nullable()->after('half_cut_price');
            $table->bigInteger('minimum_calculation')->nullable()->after('half_cut_minimum');
            $table->timestamp('archived_at')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workshop_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('store_id');
            $table->dropColumn([
                'printing_type', 'max_height', 'max_width', 'size_unit', 'quantity_minimum',
                'price_unit', 'druck_price', 'min_size', 'max_size', 'price_per_cm',
                'minimum_price', 'standard_price', 'standard_minimum', 'half_cut_price',
                'half_cut_minimum', 'minimum_calculation', 'archived_at',
            ]);
        });

        Schema::table('product_processes', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
