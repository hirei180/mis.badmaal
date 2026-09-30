<?php
declare(strict_types=1);

namespace App\Services;

/** Shared across sessions; storage must be private and writable by PHP. */
final class LoginThrottle
{
    public function __construct(private string $path, private int $maxAttempts = 5, private int $window = 900)
    {
    }

    public function reserve(string $login, string $ip): int
    {
        return $this->update(function (array &$entries) use ($login, $ip): int {
            $now = time();
            foreach ($entries as $key => $entry) {
                if ($entry['expires'] <= $now) {
                    unset($entries[$key]);
                }
            }
            $limits = [$this->accountKey($login) => $this->maxAttempts, hash('sha256', 'ip:' . $ip) => $this->maxAttempts * 5];
            $retryAfter = 0;
            foreach ($limits as $key => $limit) {
                if (($entries[$key]['count'] ?? 0) >= $limit) {
                    $retryAfter = max($retryAfter, $entries[$key]['expires'] - $now);
                }
            }
            if ($retryAfter > 0) {
                return $retryAfter;
            }
            foreach ($limits as $key => $limit) {
                $entries[$key] ??= ['count' => 0, 'expires' => $now + $this->window];
                $entries[$key]['count']++;
            }
            return 0;
        });
    }

    public function clearAccount(string $login): void
    {
        $this->update(function (array &$entries) use ($login): int {
            unset($entries[$this->accountKey($login)]);
            return 0;
        });
    }

    private function accountKey(string $login): string
    {
        return hash('sha256', 'account:' . strtolower(trim($login)));
    }

    private function update(callable $change): int
    {
        $handle = fopen($this->path, 'c+');
        if (!$handle) {
            throw new \RuntimeException('Login throttle storage is unavailable.');
        }
        try {
            if (!flock($handle, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock login throttle storage.');
            }
            $contents = stream_get_contents($handle);
            $entries = $contents === '' ? [] : json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            $result = $change($entries);
            $encoded = json_encode($entries, JSON_THROW_ON_ERROR);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, $encoded) !== strlen($encoded) || !fflush($handle)) {
                throw new \RuntimeException('Cannot save login throttle state.');
            }
            return $result;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
