<?php

namespace YakNet\RateLimiter\Policy;

use YakNet\RateLimiter\LimiterInterface;
use YakNet\RateLimiter\RateLimit;
use YakNet\RateLimiter\Storage\StorageInterface;

class SlidingWindowLimiter implements LimiterInterface
{
    private StorageInterface $storage;
    private int $limit;
    private int $window; // Sliding window size in seconds

    /**
     * @param StorageInterface $storage Pluggable storage adapter
     * @param int $limit Maximum requests allowed in the window
     * @param int $window Sliding window size in seconds (e.g. 60 for 1 minute)
     */
    public function __construct(StorageInterface $storage, int $limit, int $window)
    {
        $this->storage = $storage;
        $this->limit = $limit;
        $this->window = $window;
    }

    public function consume(string $key, int $tokens = 1): RateLimit
    {
        $limit = $this->limit;
        $window = $this->window;
        $storageKey = 'limiter:sw:' . $key;

        $isAccepted = false;
        $remaining = $limit;
        $resetTime = time() + $window;

        $this->storage->transaction($storageKey, function ($currentState) use (
            $limit,
            $window,
            $tokens,
            &$isAccepted,
            &$remaining,
            &$resetTime
        ) {
            $now = microtime(true);
            $cutoff = $now - $window;

            // Load existing timestamps or start fresh
            $timestamps = is_array($currentState) ? $currentState : [];

            // Filter out timestamps older than the sliding window cutoff
            $timestamps = array_filter($timestamps, function ($t) use ($cutoff) {
                return $t > $cutoff;
            });
            $timestamps = array_values($timestamps);

            $currentCount = count($timestamps);

            if ($currentCount + $tokens <= $limit) {
                $isAccepted = true;
                // Add new request timestamps
                for ($i = 0; $i < $tokens; $i++) {
                    $timestamps[] = $now;
                }
            } else {
                $isAccepted = false;
            }

            $remaining = max(0, $limit - count($timestamps));

            // Calculate reset time: when the oldest request expires and frees up a token
            if (count($timestamps) > 0) {
                $oldestTimestamp = $timestamps[0];
                $resetTime = (int)ceil($oldestTimestamp + $window);
            } else {
                $resetTime = time() + $window;
            }

            // Store active timestamps, expire key after window seconds of absolute inactivity
            return [$timestamps, $window];
        });

        return new RateLimit($isAccepted, $remaining, $limit, $resetTime);
    }

    public function reset(string $key): void
    {
        $this->storage->delete('limiter:sw:' . $key);
    }
}
