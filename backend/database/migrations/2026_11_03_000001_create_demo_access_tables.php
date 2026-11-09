<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_installations', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('database_name');
            $table->timestampTz('initialized_at');
        });
        Schema::create('demo_accounts', function (Blueprint $table) {
            $table->ulid('user_id')->primary();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->timestampTz('started_at')->index();
            $table->timestampTz('expires_at')->index();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->timestampTz('expiry_recorded_at')->nullable();
            $table->unsignedInteger('records_created')->default(0);
        });
        Schema::create('demo_access_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->ulid('user_id');
            $table->foreign('user_id')->references('user_id')->on('demo_accounts')->restrictOnDelete();
            $table->string('event', 30);
            $table->timestampTz('occurred_at');
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_access_events');
        Schema::dropIfExists('demo_accounts');
        Schema::dropIfExists('demo_installations');
    }
};
