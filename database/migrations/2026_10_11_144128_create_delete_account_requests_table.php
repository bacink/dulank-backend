<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delete_account_requests', function (Blueprint $table) {
            $table->id();
            $table->string('email');
            $table->string('user_name');
            $table->string('avatar')->nullable();
            $table->date('requisition_date');
            $table->date('delete_request_date')->nullable();
            $table->string('status', 20)->default('Pending');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delete_account_requests');
    }
};