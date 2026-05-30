<?php

namespace YakNet\RateLimiter;

interface LimiterInterface
{
    /**
     * Consumes a set number of tokens for a unique identifier.
     *
     * @param string $key Unique identifier (e.g. client IP, API key, user ID)
     * @param int $tokens Number of tokens to consume
     * @return RateLimit The outcome containing remaining quota and acceptance
     */
    public function consume(string $key, int $tokens = 1): RateLimit;

    /**
     * Resets the rate limit state for a unique identifier.
     *
     * @param string $key
     */
    public function reset(string $key): void;
}
