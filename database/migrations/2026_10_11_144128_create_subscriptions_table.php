<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('subscriber');
            $table->string('plan')->nullable();
            $table->string('plan_tier', 30)->default('Basic');
            $table->string('billing_cycle', 30)->default('30 Days');
            $table->string('payment_method', 40)->nullable();
            $table->bigInteger('amount')->default(0);
            $table->date('created_date')->nullable();
            $table->date('expiring_on')->nullable();
            $table->string('status', 20)->default('Unpaid');
            $table->timestamps();

            $table->index(['status', 'expiring_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};