<?php

namespace App\Models;

use App\Observers\PushSubscriptionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $p256dh
 * @property string $auth
 * @property ?string $locale
 * @property ?int $user_id
 * @property ?string $user_agent
 * @property ?Carbon $last_used_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[ObservedBy(PushSubscriptionObserver::class)]
class PushSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'locale',
        'user_id',
        'user_agent',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    /**
     * Stable hash of the endpoint URL, used as the unique key. SHA-256
     * because the endpoint is a public URL — no privacy gain from a
     * keyed hash, and SHA-256 keeps the column at a fixed 64 chars that
     * fits cleanly in a varchar index.
     */
    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
