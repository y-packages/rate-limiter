<?php

namespace YakNet\RateLimiter\Storage;

class FileStorage implements StorageInterface
{
    private string $directory;

    /**
     * @param string|null $directory Custom directory to store cache files. Defaults to system temp path.
     */
    public function __construct(?string $directory = null)
    {
        if ($directory === null) {
            $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yaknet_rate_limiter';
        }

        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);

        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . md5($key) . '.cache';
    }

    public function get(string $key): mixed
    {
        $filePath = $this->getFilePath($key);
        if (!file_exists($filePath)) {
            return null;
        }

        $fp = @fopen($filePath, 'r');
        if (!$fp) {
            return null;
        }

        @flock($fp, LOCK_SH); // Shared lock for reading

        $content = '';
        while (!feof($fp)) {
            $content .= fread($fp, 8192);
        }

        @flock($fp, LOCK_UN);
        @fclose($fp);

        if ($content === '') {
            return null;
        }

        $data = @unserialize($content);
        if ($data === false || !is_array($data)) {
            return null;
        }

        if ($data['expires_at'] !== 0 && time() > $data['expires_at']) {
            $this->delete($key);
            return null;
        }

        return $data['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $filePath = $this->getFilePath($key);
        $fp = @fopen($filePath, 'c');
        if (!$fp) {
            return;
        }

        if (@flock($fp, LOCK_EX)) {
            $expiresAt = $ttl > 0 ? time() + $ttl : 0;
            $serialized = serialize([
                'value' => $value,
                'expires_at' => $expiresAt,
            ]);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $serialized);
            @flock($fp, LOCK_UN);
        }

        @fclose($fp);
    }

    public function delete(string $key): void
    {
        $filePath = $this->getFilePath($key);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }
    }

    public function transaction(string $key, callable $callback): mixed
    {
        $filePath = $this->getFilePath($key);
        
        // Open file in read/write create mode
        $fp = @fopen($filePath, 'c+');
        if (!$fp) {
            // Fallback to in-memory-like behavior if file system is completely blocked
            return $callback(null)[0] ?? null;
        }

        @flock($fp, LOCK_EX); // Acquire exclusive lock for transaction

        // Read current content under exclusive lock
        $content = '';
        rewind($fp);
        while (!feof($fp)) {
            $content .= fread($fp, 8192);
        }

        $currentValue = null;
        if ($content !== '') {
            $data = @unserialize($content);
            if (is_array($data)) {
                if ($data['expires_at'] === 0 || time() <= $data['expires_at']) {
                    $currentValue = $data['value'];
                }
            }
        }

        // Call user callback to compute new value and TTL
        $result = $callback($currentValue);

        if ($result === null) {
            ftruncate($fp, 0);
            $newValue = null;
        } else {
            [$newValue, $ttl] = $result;
            $expiresAt = $ttl > 0 ? time() + $ttl : 0;
            $serialized = serialize([
                'value' => $newValue,
                'expires_at' => $expiresAt,
            ]);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, $serialized);
        }

        @flock($fp, LOCK_UN);
        @fclose($fp);

        if ($result === null) {
            $this->delete($key);
        }

        return $newValue;
    }

    /**
     * Clears all cache files in the directory.
     */
    public function clear(): void
    {
        $files = glob($this->directory . DIRECTORY_SEPARATOR . '*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
}
