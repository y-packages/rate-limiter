<?php

namespace YakNet\RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use YakNet\RateLimiter\Storage\FileStorage;

class FileStorageTest extends TestCase
{
    private string $tempDir;
    private FileStorage $storage;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yaknet_rate_limiter_tests_' . uniqid();
        $this->storage = new FileStorage($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->storage->clear();
        if (is_dir($this->tempDir)) {
            @rmdir($this->tempDir);
        }
    }

    public function testGetSetDelete(): void
    {
        $key = 'test_key';
        $this->assertNull($this->storage->get($key));

        // Set
        $this->storage->set($key, ['a' => 123], 10);
        $this->assertSame(['a' => 123], $this->storage->get($key));

        // Delete
        $this->storage->delete($key);
        $this->assertNull($this->storage->get($key));
    }

    public function testExpiration(): void
    {
        $key = 'expiring_key';
        
        // TTL = 1 second
        $this->storage->set($key, 'val', 1);
        $this->assertSame('val', $this->storage->get($key));

        // Wait 2.1 seconds to ensure 1-second TTL has fully ticked past in integer time()
        usleep(2100000);

        $this->assertNull($this->storage->get($key));
    }

    public function testTransaction(): void
    {
        $key = 'tx_key';

        // Transaction on empty key
        $result = $this->storage->transaction($key, function ($current) {
            $this->assertNull($current);
            return [1, 5]; // Value = 1, TTL = 5
        });

        $this->assertSame(1, $result);
        $this->assertSame(1, $this->storage->get($key));

        // Transaction on existing key
        $result2 = $this->storage->transaction($key, function ($current) {
            $this->assertSame(1, $current);
            return [$current + 2, 5]; // Value = 3, TTL = 5
        });

        $this->assertSame(3, $result2);
        $this->assertSame(3, $this->storage->get($key));

        // Transaction deleting key
        $result3 = $this->storage->transaction($key, function ($current) {
            $this->assertSame(3, $current);
            return null; // Delete
        });

        $this->assertNull($result3);
        $this->assertNull($this->storage->get($key));
    }
}
