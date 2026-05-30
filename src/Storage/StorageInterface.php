<?php

namespace YakNet\RateLimiter\Storage;

interface StorageInterface
{
    /**
     * Retrieves the stored value associated with a key.
     * Returns null if the key does not exist or has expired.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key): mixed;

    /**
     * Stores a value associated with a key, with an optional time-to-live.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time-to-live in seconds (0 for persistent)
     */
    public function set(string $key, mixed $value, int $ttl = 0): void;

    /**
     * Deletes the stored value associated with a key.
     *
     * @param string $key
     */
    public function delete(string $key): void;

    /**
     * Performs an atomic transaction (read-modify-write) on a key.
     * Prevents race conditions under high concurrency.
     *
     * The callback should have the signature:
     * function (mixed $currentValue): ?array
     * and should return [$newValue, $ttl] to write, or null to delete.
     *
     * @param string $key
     * @param callable $callback
     * @return mixed The newly written value
     */
    public function transaction(string $key, callable $callback): mixed;
}
