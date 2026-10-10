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
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('type', 30);
            $table->string('name');
            $table->string('avatar')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('address')->nullable();
            $table->date('join_date')->nullable();
            $table->boolean('subscribed')->default(false);
            $table->string('whatsapp', 30)->nullable();
            $table->string('followers_label')->nullable();
            $table->unsignedBigInteger('followers_count')->default(0);
            $table->unsignedBigInteger('following_count')->default(0);
            $table->string('image')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('partner_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('paper_count')->default(0);
            $table->unsignedInteger('printing_count')->default(0);
            $table->unsignedInteger('lamination_count')->default(0);
            $table->unsignedInteger('die_cutting_count')->default(0);
            $table->unsignedInteger('foil_count')->default(0);
            $table->unsignedInteger('group_count')->default(0);
            $table->unsignedInteger('type_count')->default(0);
            $table->timestamps();
        });

        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('Active');
            $table->boolean('published')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('moderation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->unsignedInteger('duration')->nullable();
            $table->boolean('notify')->default(false);
            $table->text('message')->nullable();
            $table->string('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('paper_groups', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20)->default('self');
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('name');
            $table->string('merk')->nullable();
            $table->string('price_type', 10)->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('paper_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('name');
            $table->string('merk')->nullable();
            $table->decimal('gsm', 10, 2)->nullable();
            $table->string('dimension')->nullable();
            $table->string('unit', 20)->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->string('unit_stock', 20)->nullable();
            $table->bigInteger('price')->nullable();
            $table->decimal('min_order', 15, 2)->nullable();
            $table->decimal('multiple', 15, 2)->nullable();
            $table->boolean('published')->default(true);
            $table->boolean('active')->default(true);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('paper_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paper_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('name');
            $table->decimal('min_order', 15, 2)->nullable();
            $table->decimal('multiple', 15, 2)->nullable();
            $table->bigInteger('price')->default(0);
            $table->boolean('published')->default(true);
            $table->boolean('active')->default(true);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('paper_sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paper_group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source')->nullable();
            $table->string('name');
            $table->string('dimension')->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->string('unit', 20)->nullable();
            $table->boolean('published')->default(true);
            $table->boolean('active')->default(true);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 20)->default('self');
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30);
            $table->string('source')->nullable();
            $table->string('name');
            $table->string('type', 40)->nullable();
            $table->unsignedInteger('colors')->nullable();
            $table->string('size')->nullable();
            $table->string('max_area')->nullable();
            $table->bigInteger('plate_cost')->nullable();
            $table->bigInteger('minim')->nullable();
            $table->bigInteger('druck')->nullable();
            $table->bigInteger('price')->nullable();
            $table->bigInteger('unit_price')->nullable();
            $table->bigInteger('rate_cm')->nullable();
            $table->bigInteger('putus_rate')->nullable();
            $table->bigInteger('putus_minim')->nullable();
            $table->bigInteger('kiss_rate')->nullable();
            $table->bigInteger('kiss_minim')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('active')->default(false);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('die_cutting_blades', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('price_per_cm')->default(0);
            $table->boolean('published')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('calculator_components', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 30);
            $table->string('name');
            $table->bigInteger('value')->nullable();
            $table->bigInteger('rate')->nullable();
            $table->bigInteger('minim')->nullable();
            $table->string('unit', 30)->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('profit_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('min_qty');
            $table->unsignedInteger('max_qty')->nullable();
            $table->decimal('profit_pos_percent', 5, 2);
            $table->decimal('profit_webstore_percent', 5, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profit_tiers');
        Schema::dropIfExists('calculator_components');
        Schema::dropIfExists('die_cutting_blades');
        Schema::dropIfExists('machines');
        Schema::dropIfExists('paper_sizes');
        Schema::dropIfExists('paper_prices');
        Schema::dropIfExists('paper_types');
        Schema::dropIfExists('paper_groups');
        Schema::dropIfExists('moderation_histories');
        Schema::dropIfExists('listings');
        Schema::dropIfExists('partner_metrics');
        Schema::dropIfExists('partners');
    }
};
