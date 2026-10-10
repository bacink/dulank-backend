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
        Schema::create('request_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('no_request', 60)->unique();
            $table->string('customer');
            $table->string('email')->nullable();
            $table->string('telp', 30)->nullable();
            $table->date('date');
            $table->string('status', 20)->default('Pending');
            $table->date('due_date')->nullable();
            $table->string('payment_term')->nullable();
            $table->json('document')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_quotations');
    }
};
