<?php

require_once __DIR__ . '/../vendor/autoload.php';

use YakNet\RateLimiter\Policy\SlidingWindowLimiter;
use YakNet\RateLimiter\Policy\TokenBucketLimiter;
use YakNet\RateLimiter\Storage\FileStorage;

function printHeader(string $title): void
{
    echo "\n\033[1;36m==================================================\033[0m\n";
    echo "\033[1;36m   " . str_pad(strtoupper($title), 44, ' ', STR_PAD_BOTH) . "   \033[0m\n";
    echo "\033[1;36m==================================================\033[0m\n";
}

function logResult(int $reqNum, \YakNet\RateLimiter\RateLimit $limit): void
{
    $time = date('H:i:s');
    if ($limit->isAccepted()) {
        echo "[\033[36m{$time}\033[0m] \033[32m✔ Request #{$reqNum} ACCEPTED\033[0m | Remaining: \033[1;33m{$limit->getRemaining()}\033[0m/{$limit->getLimit()} | Reset in: {$limit->getRetryAfter()}s\n";
    } else {
        echo "[\033[36m{$time}\033[0m] \033[31m✖ Request #{$reqNum} THROTTLED\033[0m | Remaining: \033[1;33m{$limit->getRemaining()}\033[0m/{$limit->getLimit()} | \033[1;35mRetry after: {$limit->getRetryAfter()}s\033[0m\n";
    }
}

// 1. Setup FileStorage (works on any server with zero-dependencies!)
$storage = new FileStorage();
$storage->clear(); // Fresh start

// -----------------------------------------------------------------
// DEMO 1: Token Bucket Algorithm
// -----------------------------------------------------------------
printHeader("DEMO 1: Token Bucket Limiter");
echo "Configuration: Capacity = \033[1;33m5\033[0m, Refill Rate = \033[1;33m1 token/second\033[0m\n";
echo "Simulating 8 rapid hits in a row...\n\n";

$limiter = new TokenBucketLimiter($storage, 5, 1.0);
$clientKey = 'demo_client_ip';

for ($i = 1; $i <= 8; $i++) {
    $limit = $limiter->consume($clientKey, 1);
    logResult($i, $limit);
    usleep(100000); // 100ms gap
}

echo "\n\033[1;33mWaiting 2.5 seconds for the bucket to refill...\033[0m\n";
usleep(2500000); // 2.5 seconds

echo "\nSimulating 3 more rapid hits...\n\n";
for ($i = 9; $i <= 11; $i++) {
    $limit = $limiter->consume($clientKey, 1);
    logResult($i, $limit);
    usleep(100000);
}

// -----------------------------------------------------------------
// DEMO 2: Sliding Window Algorithm
// -----------------------------------------------------------------
printHeader("DEMO 2: Sliding Window Limiter");
echo "Configuration: Limit = \033[1;33m3 requests\033[0m, Rolling Window = \033[1;33m3 seconds\033[0m\n";
echo "Simulating 5 rapid hits...\n\n";

$slidingLimiter = new SlidingWindowLimiter($storage, 3, 3);
$slidingKey = 'demo_sliding_client';

for ($i = 1; $i <= 5; $i++) {
    $limit = $slidingLimiter->consume($slidingKey, 1);
    logResult($i, $limit);
    usleep(100000);
}

echo "\n\033[1;33mWaiting 3.2 seconds for the rolling window to completely slide past...\033[0m\n";
usleep(3200000);

echo "\nSimulating 2 more hits after window shift...\n\n";
for ($i = 6; $i <= 7; $i++) {
    $limit = $slidingLimiter->consume($slidingKey, 1);
    logResult($i, $limit);
    usleep(100000);
}

echo "\n\033[1;32m✔ Rate Limiter Demonstration Complete!\033[0m\n\n";
