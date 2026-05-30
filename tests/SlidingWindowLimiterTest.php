<?php

namespace YakNet\RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use YakNet\RateLimiter\Policy\SlidingWindowLimiter;
use YakNet\RateLimiter\Storage\InMemoryStorage;

class SlidingWindowLimiterTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    public function testSlidingWindowBurstAndThrottling(): void
    {
        // Limit = 3, Window = 2 seconds
        $limiter = new SlidingWindowLimiter($this->storage, 3, 2);
        $key = 'ip_127_0_0_1';

        // Send 3 requests immediately
        $this->assertTrue($limiter->consume($key)->isAccepted());
        $this->assertTrue($limiter->consume($key)->isAccepted());
        
        $limit3 = $limiter->consume($key);
        $this->assertTrue($limit3->isAccepted());
        $this->assertSame(0, $limit3->getRemaining());

        // 4th request must be throttled
        $limit4 = $limiter->consume($key);
        $this->assertFalse($limit4->isAccepted());
        $this->assertSame(0, $limit4->getRemaining());
    }

    public function testSlidingExpiration(): void
    {
        // Limit = 2, Window = 1 second
        $limiter = new SlidingWindowLimiter($this->storage, 2, 1);
        $key = 'ip_10_0_0_1';

        // Fill the window
        $this->assertTrue($limiter->consume($key)->isAccepted());
        $this->assertTrue($limiter->consume($key)->isAccepted());
        $this->assertFalse($limiter->consume($key)->isAccepted());

        // Wait 1.1 seconds for all timestamps to slide out of the window
        usleep(1100000); // 1.1s

        // Should accept requests again
        $limit = $limiter->consume($key);
        $this->assertTrue($limit->isAccepted());
        $this->assertSame(1, $limit->getRemaining());
    }

    public function testReset(): void
    {
        $limiter = new SlidingWindowLimiter($this->storage, 2, 1);
        $key = 'ip_reset';

        $limiter->consume($key);
        $limiter->consume($key);
        $this->assertFalse($limiter->consume($key)->isAccepted());

        $limiter->reset($key);

        $this->assertTrue($limiter->consume($key)->isAccepted());
    }
}
