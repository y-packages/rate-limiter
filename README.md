# YakNet Rate Limiter Component

[![PHP Version Support](https://img.shields.io/badge/php-%3E%3D%208.2-blue.svg?style=flat-square)](https://packagist.org/packages/yaknet/rate-limiter)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![PHPStan Analysis](https://img.shields.io/badge/PHPStan-level%205%20clean-purple.svg?style=flat-square)](https://phpstan.org/)
[![Tests Status](https://img.shields.io/badge/tests-passing-brightgreen.svg?style=flat-square)](https://phpunit.de/)

A high-performance, zero-dependency, pure PHP rate limiting component. Supports standard rate-limiting algorithms and pluggable storage backends (vital for both persistent processes like WebSockets and shared-nothing environments like PHP-FPM).

---

## Features

- **⚡ Zero External Production Dependencies:** Extremely lightweight, fast, and easy to embed.
- **🛡️ Race-Condition Free:** Leverages unique transactional read-modify-write logic using exclusive locks (`flock`) on files and optimistic concurrency controls (`WATCH`/`MULTI`/`EXEC`) on Redis.
- **🔄 Pluggable Storage Adapters:**
  - `InMemoryStorage`: RAM-based array. Extremely fast, perfect for tests and long-running services (e.g. WebSocket servers).
  - `FileStorage`: Local disk-based storage with robust file-locking. Works out of the box on *any* server (shared hosting, basic VPS) with zero external setup or engines.
  - `RedisStorage`: Scalable storage compatible with both native PHP `Redis` extension (Phpredis) and userland `Predis` client library.
- **📈 Advanced Algorithms:**
  - **Token Bucket:** Allows quick bursts up to a capacity limit and refills tokens continuously based on high-resolution elapsed time.
  - **Sliding Window:** Tracks logs in a rolling sliding window, preventing boundaries/fixed-window request resets.

---

## Installation

Add this package to your project using Composer (ensure your local repository mapping is configured):

```bash
composer require yaknet/rate-limiter
```

---

## Quick Start

### 1. Token Bucket Limiter (Using Zero-Dependency FileStorage)

Great for allowing bursts of traffic while ensuring a steady continuous refill rate:

```php
<?php

require 'vendor/autoload.php';

use YakNet\RateLimiter\Policy\TokenBucketLimiter;
use YakNet\RateLimiter\Storage\FileStorage;

// Initialize zero-dependency file-based storage
$storage = new FileStorage();

// Capacity = 10 tokens, Refills at 2.0 tokens every second
$limiter = new TokenBucketLimiter($storage, 10, 2.0);

$clientIp = '127.0.0.1';

// Consume 1 token
$limit = $limiter->consume($clientIp, 1);

if ($limit->isAccepted()) {
    echo "✔ Request accepted! Remaining quota: " . $limit->getRemaining() . "\n";
} else {
    echo "✖ Request throttled! Please wait " . $limit->getRetryAfter() . " seconds.\n";
}
```

### 2. Sliding Window Limiter (Using Fast InMemoryStorage)

Perfect for sliding-log tracking inside persistent processes (e.g. your WebSocket loops):

```php
<?php

use YakNet\RateLimiter\Policy\SlidingWindowLimiter;
use YakNet\RateLimiter\Storage\InMemoryStorage;

// RAM storage (values persist within the active long-running thread)
$storage = new InMemoryStorage();

// Limit = 5 requests per 10-second rolling window
$limiter = new SlidingWindowLimiter($storage, 5, 10);

$userId = 'user_99';
$limit = $limiter->consume($userId, 1);

if ($limit->isAccepted()) {
    // Process request
}
```

### 3. Distributed Redis Storage Setup

Compatible with both **Phpredis** and **Predis**:

```php
use YakNet\RateLimiter\Policy\TokenBucketLimiter;
use YakNet\RateLimiter\Storage\RedisStorage;

// Using native PHP \Redis or Predis\Client
$redis = new \Redis();
$redis->connect('127.0.0.1', 6379);

$storage = new RedisStorage($redis);
$limiter = new TokenBucketLimiter($storage, 100, 10.0);
```

---

## Verification & Testing

Verify that all algorithms and lock contention structures pass successfully:

```bash
composer test
```

Perform static analysis with PHPStan:

```bash
composer analyze
```

---

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
