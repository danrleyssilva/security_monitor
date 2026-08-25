<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\Database;
use App\Models\MonitoringRepository;
use App\Services\NetworkMonitor;
use Throwable;

final class MonitoringController
{
    public function index(): void
    {
        $nvrRows = [];
        $error = null;
        $updatedAt = date('d/m/Y H:i:s');

        try {
            $repository = new MonitoringRepository(Database::connect());
            $monitor = new NetworkMonitor();
            $failureCounts = $repository->getFailureCounts();
            $nvrs = $repository->getNvrs();
            $camerasByNvr = [];
            $ips = [];

            foreach ($nvrs as $nvr) {
                $cameras = $repository->getCamerasByNvr($nvr['id']);
                $camerasByNvr[(string) $nvr['id']] = $cameras;
                $ips[] = trim((string) $nvr['ip']);
                foreach ($cameras as $camera) {
                    $ips[] = trim((string) $camera['ip']);
                }
            }
            $pingResults = $monitor->checkMany($ips);

            foreach ($nvrs as $nvr) {
                $cameras = $camerasByNvr[(string) $nvr['id']];
                $offlineCameras = [];
                $activeCameras = 0;

                foreach ($cameras as $camera) {
                    $ip = trim((string) $camera['ip']);
                    $cameraKey = $nvr['id'] . '|' . $ip;
                    if ($pingResults[$ip] ?? false) {
                        $repository->clearFailure($cameraKey);
                        $activeCameras++;
                        continue;
                    }

                    $failureCount = min(3, ((int) ($failureCounts[$cameraKey] ?? 0)) + 1);
                    $repository->saveFailure($cameraKey, $failureCount);
                    if ($failureCount < 3) {
                        $activeCameras++;
                        continue;
                    }

                    $imageName = filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip . '.jpg' : null;
                    $offlineCameras[] = [
                        'ip' => $ip ?: 'IP nao informado',
                        'image' => $imageName !== null && is_file(dirname(__DIR__, 2) . '/cameras/' . $imageName),
                    ];
                }

                $nvrRows[] = [
                    'id' => $nvr['id'],
                    'name' => $nvr['name'] ?: 'NVR #' . $nvr['id'],
                    'ip' => $nvr['ip'],
                    'online' => $pingResults[trim((string) $nvr['ip'])] ?? false,
                    'active' => $activeCameras,
                    'total' => count($cameras),
                    'offline' => $offlineCameras,
                ];
            }
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $onlineNvrs = count(array_filter($nvrRows, static fn(array $nvr): bool => $nvr['online']));
        $offlineCamerasCount = array_sum(array_map(static fn(array $nvr): int => count($nvr['offline']), $nvrRows));

        require dirname(__DIR__) . '/Views/monitoring/index.php';
    }
}
