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
        Schema::table('my_incentives', function (Blueprint $table) {
            $table->foreignId('job_branch_id')->nullable()->after('job_order_id')
                ->constrained('job_branches')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('my_incentives', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_branch_id');
        });
    }
};
