<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['stores', 'suppliers'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->string('photo_path', 2048)->nullable());
        }
    }

    public function down(): void
    {
        foreach (['stores', 'suppliers'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('photo_path'));
        }
    }
};
