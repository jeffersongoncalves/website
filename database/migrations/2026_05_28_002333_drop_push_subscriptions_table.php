<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }

    public function down(): void
    {
        // Web Push feature removed — no reversal.
    }
};
