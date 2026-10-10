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
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('values')->nullable();
            $table->unsignedInteger('item_used')->default(0);
            $table->string('status', 20)->default('Active');
            $table->timestamps();
        });

        Schema::create('user_admins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('admin_code', 60)->unique();
            $table->string('role', 60)->default('Admin');
            $table->json('stores')->nullable();
            $table->string('status', 20)->default('Active');
            $table->text('descriptions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_admins');
        Schema::dropIfExists('variants');
    }
};
