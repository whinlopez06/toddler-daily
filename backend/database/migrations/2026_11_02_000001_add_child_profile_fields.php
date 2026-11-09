<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('children', function (Blueprint $table) {
            $table->string('first_name', 100)->nullable();
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100)->nullable();
            $table->string('suffix', 20)->nullable();
            $table->string('gender', 10)->nullable();
        });
    }
    public function down(): void {
        Schema::table('children', fn (Blueprint $table) => $table->dropColumn(['first_name','middle_name','last_name','suffix','gender']));
    }
};
