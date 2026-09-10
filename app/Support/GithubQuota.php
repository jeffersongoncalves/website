<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Redis;

final class GithubQuota
{
    /** Reserva o próximo slot livre e devolve o delay, em segundos, para o job. */
    public static function reserve(int $cost = 1, string $bucket = 'github-api', int $perHour = 4500): int
    {
        $now = microtime(true);

        $script = <<<'LUA'
            local now  = tonumber(ARGV[1])
            local step = tonumber(ARGV[2])
            local nxt  = tonumber(redis.call('GET', KEYS[1]) or '0')
            if nxt < now then nxt = now end
            redis.call('SET', KEYS[1], tostring(nxt + step), 'EX', 172800)
            return tostring(nxt)
        LUA;

        // Redis::eval()'s stub resolves to the raw ext-redis signature
        // (script, array $args, int $num_keys), but Laravel's actual runtime
        // eval() wrapper takes (script, numberOfKeys, ...arguments) — the two
        // disagree, so go through the untyped command() dispatch instead,
        // matching ext-redis's native call shape directly.
        $slot = (float) Redis::connection()->command('eval', [
            $script,
            ["quota-slot:{$bucket}", $now, $cost * 3600 / $perHour],
            1,
        ]);

        return max(0, (int) ceil($slot - $now));
    }
}
