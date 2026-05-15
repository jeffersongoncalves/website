<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readme_caches', function (Blueprint $table) {
            $table->id();
            $table->string('repo');
            $table->string('ref')->default('default');
            $table->string('etag')->nullable();
            $table->string('default_branch')->nullable();
            $table->string('html_path')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['repo', 'ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readme_caches');
    }
};
