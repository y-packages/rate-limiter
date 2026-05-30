<?php

namespace YakNet\RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use YakNet\RateLimiter\Policy\TokenBucketLimiter;
use YakNet\RateLimiter\Storage\InMemoryStorage;

class TokenBucketLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    public function testBasicConsumption(): void
    {
        // Capacity = 5, Refill = 1 token/sec
        $limiter = new TokenBucketLimiter($this->storage, 5, 1.0);
        $key = 'user_1';

        // Initial check: full capacity
        $limit = $limiter->consume($key, 1);
        $this->assertTrue($limit->isAccepted());
        $this->assertSame(4, $limit->getRemaining());
        $this->assertSame(5, $limit->getLimit());

        // Consume 3 more tokens
        $limit2 = $limiter->consume($key, 3);
        $this->assertTrue($limit2->isAccepted());
        $this->assertSame(1, $limit2->getRemaining());

        // Try consuming 2 tokens (only 1 available) - should be rejected
        $limit3 = $limiter->consume($key, 2);
        $this->assertFalse($limit3->isAccepted());
        $this->assertSame(1, $limit3->getRemaining()); // Quota remains unchanged on failure
        $this->assertGreaterThan(0, $limit3->getRetryAfter());
    }

    public function testBucketRefill(): void
    {
        // Capacity = 3, Refill = 2 tokens/sec
        $limiter = new TokenBucketLimiter($this->storage, 3, 2.0);
        $key = 'user_2';

        // Drain the bucket completely
        $limit = $limiter->consume($key, 3);
        $this->assertTrue($limit->isAccepted());
        $this->assertSame(0, $limit->getRemaining());

        // Immediate next request should fail
        $limit2 = $limiter->consume($key, 1);
        $this->assertFalse($limit2->isAccepted());

        // Wait 500ms (refills 1 token: 0.5 sec * 2 tokens/sec = 1 token)
        usleep(550000); // 550ms for safety margins in time tests

        $limit3 = $limiter->consume($key, 1);
        $this->assertTrue($limit3->isAccepted(), 'Bucket should have refilled 1 token after 500ms');
    }

    public function testCapacityCeiling(): void
    {
        // Capacity = 2, Refill = 10 tokens/sec
        $limiter = new TokenBucketLimiter($this->storage, 2, 10.0);
        $key = 'user_3';

        // Sleep 200ms -> theoretically 2 tokens refilled.
        usleep(200000);

        // Consume 2 tokens
        $limit = $limiter->consume($key, 2);
        $this->assertTrue($limit->isAccepted());
        $this->assertSame(0, $limit->getRemaining());

        // Consume 1 token - fails
        $limit2 = $limiter->consume($key, 1);
        $this->assertFalse($limit2->isAccepted());
    }

    public function testReset(): void
    {
        $limiter = new TokenBucketLimiter($this->storage, 3, 1.0);
        $key = 'user_4';

        $limiter->consume($key, 3);
        $this->assertFalse($limiter->consume($key, 1)->isAccepted());

        // Reset
        $limiter->reset($key);

        // Should be full again
        $this->assertTrue($limiter->consume($key, 1)->isAccepted());
    }
}
