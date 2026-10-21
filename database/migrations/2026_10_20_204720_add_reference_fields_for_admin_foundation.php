<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_admins', fn (Blueprint $table) => $table->boolean('all_stores')->default(false));
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_changed_at')->nullable();
        });
        foreach (['regencies', 'districts'] as $name) {
            Schema::table($name, function (Blueprint $table): void {
                $table->string('status', 20)->default('Active');
            });
        }
        Schema::table('addresses', function (Blueprint $table): void {
            $table->foreignId('province_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('regency_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('district_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('employees', function (Blueprint $table): void {
            $table->string('emergency_contact2_name')->nullable();
            $table->string('emergency_contact2_phone', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', fn (Blueprint $table) => $table->dropColumn(['emergency_contact2_name', 'emergency_contact2_phone']));
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('district_id');
            $table->dropConstrainedForeignId('regency_id');
            $table->dropConstrainedForeignId('province_id');
        });
        foreach (['regencies', 'districts'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('status'));
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_changed_at'));
        Schema::table('user_admins', fn (Blueprint $table) => $table->dropColumn('all_stores'));
    }
};
