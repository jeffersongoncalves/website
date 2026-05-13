<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('repo')->nullable();
            $table->string('category');
            $table->json('title')->nullable();
            $table->json('description');
            $table->json('content')->nullable();
            $table->json('versions')->nullable();
            $table->json('stack')->nullable();
            $table->unsignedInteger('stars')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->string('downloads_label')->nullable();
            $table->string('license')->default('MIT');
            $table->string('github_url')->nullable();
            $table->string('packagist_url')->nullable();
            $table->string('docs_url')->nullable();
            $table->string('demo_url')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'featured']);
            $table->index('category');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
