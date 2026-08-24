<?php

declare(strict_types=1);

namespace App\Services;

final class NetworkMonitor
{
    public function respondsToPing(string $ip, int $attempts = 1): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $attempts = max(1, $attempts);
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $command = $isWindows
            ? 'ping -n ' . $attempts . ' -w 800 ' . escapeshellarg($ip)
            : 'ping -c ' . $attempts . ' -W 1 ' . escapeshellarg($ip);
        exec($command, $output, $code);

        return $code === 0;
    }
}
