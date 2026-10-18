<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->json('name');                       // {"en": "...", "ar": "..."}
            $table->string('status')->default('active'); // active | inactive | suspended
            $table->string('domain')->nullable()->unique();
            $table->json('settings')->nullable();       // branding/theme/modules/wallet/points/app (contract §5)
            $table->text('gateway_credentials')->nullable(); // encrypted:array cast
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
