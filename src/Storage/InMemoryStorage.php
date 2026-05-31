<?php

namespace YakNet\RateLimiter\Storage;

class InMemoryStorage implements StorageInterface
{
    /** @var array<string, array{value: mixed, expires_at: int}> */
    private array $data = [];

    public function get(string $key): mixed
    {
        if (!isset($this->data[$key])) {
            return null;
        }

        $entry = $this->data[$key];
        if ($entry['expires_at'] !== 0 && time() > $entry['expires_at']) {
            $this->delete($key);
            return null;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $expiresAt = $ttl > 0 ? time() + $ttl : 0;
        
        $this->data[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->data[$key]);
    }

    public function transaction(string $key, callable $callback): mixed
    {
        $currentValue = $this->get($key);
        $result = $callback($currentValue);

        if ($result === null) {
            $this->delete($key);
            return null;
        }

        [$newValue, $ttl] = $result;
        $this->set($key, $newValue, $ttl);
        
        return $newValue;
    }

    /**
     * Clears all stored data (primarily for testing purposes).
     */
    public function clear(): void
    {
        $this->data = [];
    }
}
