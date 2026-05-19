<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            // `endpoint` is the Push Service URL the browser hands us. Long
            // enough that we store it in `text`, but we still need uniqueness
            // — kept on a sha256 hash column since MySQL/MariaDB can't index
            // an unbounded TEXT outright. Postgres allows it but the hash is
            // portable across both engines.
            $table->text('endpoint');
            $table->string('endpoint_hash', 64)->unique();
            // p256dh / auth are the EC public key + auth secret needed to
            // encrypt payloads per RFC 8291. Both are base64url strings.
            $table->string('p256dh', 255);
            $table->string('auth', 255);
            // Browser-reported locale + optional user_id link. Both nullable
            // so anonymous visitors can subscribe without an account.
            $table->string('locale', 8)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
