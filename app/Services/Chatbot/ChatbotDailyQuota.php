<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ChatbotDailyQuota
{
    public const LIMIT = 5;

    public function identity(string|int|null $userId, ?string $guestId): string
    {
        return $userId !== null ? 'user:'.$userId : 'guest:'.($guestId ?? Str::random(40));
    }

    public function quota(string $identity): array
    {
        $now = now('Asia/Manila');
        $key = $this->key($identity, $now->toDateString());
        $used = (int) Cache::store()->get($key, 0);

        return ['limit' => self::LIMIT, 'remaining' => max(0, self::LIMIT - $used), 'resets_at' => $now->copy()->addDay()->startOfDay()->toIso8601String()];
    }

    public function admit(string $identity): array
    {
        $now = now('Asia/Manila');
        $key = $this->key($identity, $now->toDateString());
        $store = Cache::store();
        $count = $store->lock($key.':lock', 10)->block(5, function () use ($store, $key, $now) {
            $used = (int) $store->get($key, 0);
            if ($used >= self::LIMIT) {
                return 0;
            }
            $store->put($key, $used + 1, $now->copy()->addDay()->startOfDay());

            return $used + 1;
        });

        return ['admitted' => $count > 0 && $count <= self::LIMIT, 'quota' => ['limit' => self::LIMIT, 'remaining' => $count === 0 ? 0 : max(0, self::LIMIT - $count), 'resets_at' => $now->copy()->addDay()->startOfDay()->toIso8601String()]];
    }

    private function key(string $identity, string $date): string
    {
        return 'chatbot:daily:'.hash_hmac('sha256', $identity.'|'.$date, (string) config('app.key'));
    }
}
