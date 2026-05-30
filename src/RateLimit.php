<?php

namespace YakNet\RateLimiter;

class RateLimit
{
    private bool $accepted;
    private int $remaining;
    private int $limit;
    private int $resetTime;

    public function __construct(bool $accepted, int $remaining, int $limit, int $resetTime)
    {
        $this->accepted = $accepted;
        $this->remaining = max(0, $remaining);
        $this->limit = $limit;
        $this->resetTime = $resetTime;
    }

    /**
     * Whether the request was accepted within the rate limits.
     *
     * @return bool
     */
    public function isAccepted(): bool
    {
        return $this->accepted;
    }

    /**
     * The number of remaining allowed requests/tokens within the current window/bucket.
     *
     * @return int
     */
    public function getRemaining(): int
    {
        return $this->remaining;
    }

    /**
     * The maximum number of allowed requests/tokens in the window/bucket.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * The Unix timestamp when the rate limit window will reset or completely refill.
     *
     * @return int
     */
    public function getResetTime(): int
    {
        return $this->resetTime;
    }

    /**
     * The number of seconds the client should wait before making another request.
     * Returns 0 if the request was accepted.
     *
     * @return int
     */
    public function getRetryAfter(): int
    {
        if ($this->accepted) {
            return 0;
        }

        return max(0, $this->resetTime - time());
    }
}
