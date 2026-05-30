<?php

namespace YakNet\RateLimiter\Storage;

class RedisStorage implements StorageInterface
{
    private $redis;

    /**
     * @param object $redis An instance of \Redis (Phpredis) or \Predis\Client
     */
    public function __construct(object $redis)
    {
        $this->redis = $redis;
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        if ($value === false || $value === null) {
            return null;
        }

        return @unserialize($value);
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $serialized = serialize($value);

        if ($ttl > 0) {
            $this->redis->setex($key, $ttl, $serialized);
        } else {
            $this->redis->set($key, $serialized);
        }
    }

    public function delete(string $key): void
    {
        $this->redis->del($key);
    }

    public function transaction(string $key, callable $callback): mixed
    {
        $maxRetries = 10;
        $attempt = 0;

        while ($attempt < $maxRetries) {
            $attempt++;

            // Watch key for optimistic locking
            $this->redis->watch($key);

            // Fetch current state
            $currentValue = $this->get($key);

            // Compute new value and TTL
            $result = $callback($currentValue);

            // Start pipeline/transaction
            // In Phpredis: multi() starts transaction. In Predis: multi() also starts transaction.
            $tx = $this->redis->multi();

            if ($result === null) {
                // Delete the key
                if (method_exists($tx, 'del')) {
                    $tx->del($key);
                } else {
                    $tx->delete($key);
                }
            } else {
                [$newValue, $ttl] = $result;
                $serialized = serialize($newValue);

                if ($ttl > 0) {
                    $tx->setex($key, $ttl, $serialized);
                } else {
                    $tx->set($key, $serialized);
                }
            }

            // Execute transaction
            $execResult = $tx->exec();

            // In Redis, if WATCHed key changes, exec() returns null/empty/false
            if ($execResult !== false && $execResult !== null) {
                return $result === null ? null : $result[0];
            }

            // If transaction aborted, backoff briefly and retry
            usleep(random_int(1000, 5000)); // 1-5ms random jitter backoff
        }

        throw new \RuntimeException("Redis transaction failed after {$maxRetries} attempts due to write collision on key: {$key}");
    }
}
