<?php

namespace YakNet\RateLimiter\Policy;

use YakNet\RateLimiter\LimiterInterface;
use YakNet\RateLimiter\RateLimit;
use YakNet\RateLimiter\Storage\StorageInterface;

class TokenBucketLimiter implements LimiterInterface
{
    private StorageInterface $storage;
    private int $capacity;
    private float $refillRate; // Tokens refilled per second

    /**
     * @param StorageInterface $storage Pluggable storage adapter
     * @param int $capacity Maximum tokens the bucket can hold
     * @param float $refillRate Tokens refilled per second (e.g. 2.0 = 2 tokens refilled every second)
     */
    public function __construct(StorageInterface $storage, int $capacity, float $refillRate)
    {
        $this->storage = $storage;
        $this->capacity = $capacity;
        $this->refillRate = $refillRate;
    }

    public function consume(string $key, int $tokens = 1): RateLimit
    {
        $capacity = $this->capacity;
        $refillRate = $this->refillRate;
        $storageKey = 'limiter:tb:' . $key;

        // TTL calculation: Time required to completely refill from 0 to capacity
        $ttl = (int)ceil($capacity / $refillRate);

        // Local state trackers to capture computed outcomes from within transaction callback
        $isAccepted = false;
        $remainingTokens = $capacity;
        $resetTime = time();

        $this->storage->transaction($storageKey, function ($currentState) use (
            $capacity,
            $refillRate,
            $tokens,
            $ttl,
            &$isAccepted,
            &$remainingTokens,
            &$resetTime
        ) {
            $now = microtime(true);

            if ($currentState === null || !is_array($currentState)) {
                $oldTokens = (float)$capacity;
                $lastRefilled = $now;
            } else {
                $oldTokens = (float)$currentState['tokens'];
                $lastRefilled = (float)$currentState['last_refilled'];
            }

            // Calculate fractional refilling based on time elapsed
            $elapsed = max(0.0, $now - $lastRefilled);
            $refilled = $elapsed * $refillRate;
            $currentTokens = min((float)$capacity, $oldTokens + $refilled);

            if ($currentTokens >= $tokens) {
                $isAccepted = true;
                $currentTokens -= $tokens;
                $lastRefilled = $now;
            } else {
                $isAccepted = false;
            }

            $remainingTokens = (int)floor($currentTokens);
            
            // Calculate when the bucket will completely refill
            $secondsToFull = ($capacity - $currentTokens) / $refillRate;
            $resetTime = time() + (int)ceil($secondsToFull);

            // Return state to persist and TTL
            return [
                [
                    'tokens' => $currentTokens,
                    'last_refilled' => $lastRefilled,
                ],
                $ttl
            ];
        });

        return new RateLimit($isAccepted, $remainingTokens, $capacity, $resetTime);
    }

    public function reset(string $key): void
    {
        $this->storage->delete('limiter:tb:' . $key);
    }
}
