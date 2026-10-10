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
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('flag')->nullable();
            $table->boolean('rtl')->default(false);
            $table->unsignedInteger('progress')->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('language_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('language_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['language_id', 'key']);
        });

        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->string('module', 60);
            $table->string('label');
            $table->string('name', 60);
            $table->string('type', 30)->default('text');
            $table->json('options')->nullable();
            $table->boolean('required')->default(false);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('ban_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->string('reason')->nullable();
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ban_ips');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('language_translations');
        Schema::dropIfExists('languages');
    }
};
