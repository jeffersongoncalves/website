<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('repos')->default(0);
            $table->unsignedInteger('filament')->default(0);
            $table->unsignedInteger('laravel')->default(0);
            $table->unsignedInteger('starter')->default(0);
            $table->unsignedInteger('tool')->default(0);
            $table->unsignedBigInteger('stars')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->unsignedInteger('followers')->default(0);
            $table->unsignedInteger('public_sponsors')->default(0);
            $table->json('contributions')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stats');
    }
};
