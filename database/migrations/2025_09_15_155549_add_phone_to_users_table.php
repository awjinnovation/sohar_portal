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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number', 20)->nullable()->unique()->after('email');
            $table->string('otp_secret')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('device_token')->nullable();
            $table->string('preferred_language', 2)->default('en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'otp_secret', 'phone_verified_at', 'device_token', 'preferred_language']);
        });
    }
};
