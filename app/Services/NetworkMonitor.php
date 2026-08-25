<?php

declare(strict_types=1);

namespace App\Services;

final class NetworkMonitor
{
    // Muitos pings simultâneos podem causar descarte de ICMP em NVRs/switches.
    // 12 mantém a página rápida sem sobrecarregar os equipamentos.
    private const MAX_CONCURRENT_PINGS = 12;
    private const PROCESS_TIMEOUT_SECONDS = 3.0;

    /** @var array<string, bool> */
    private array $results = [];

    /**
     * Executa os pings em paralelo. Sem isso, uma câmera indisponível segura a
     * requisição por até um segundo e uma lista grande acaba em timeout HTTP.
     *
     * @param list<string> $ips
     * @return array<string, bool>
     */
    public function checkMany(array $ips): array
    {
        $pending = [];
        foreach (array_unique($ips) as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $this->results[$ip] = false;
            } elseif (!array_key_exists($ip, $this->results)) {
                $pending[] = $ip;
            }
        }

        if (!$pending) {
            return $this->results;
        }

        $this->runPings($pending);

        // Uma segunda tentativa só para as falhas evita falso negativo por
        // perda transitória de pacote ou por limitação de ICMP no equipamento.
        $retry = array_values(array_filter(
            $pending,
            fn(string $ip): bool => !($this->results[$ip] ?? false)
        ));
        if ($retry) {
            usleep(200_000);
            $this->runPings($retry);
        }

        return $this->results;
    }

    /** @param list<string> $pending */
    private function runPings(array $pending): void
    {
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $running = [];

        while ($pending || $running) {
            while ($pending && count($running) < self::MAX_CONCURRENT_PINGS) {
                $ip = array_shift($pending);
                $command = $isWindows
                    ? ['ping', '-n', '1', '-w', '800', $ip]
                    : ['ping', '-c', '1', '-W', '1', $ip];
                $pipes = [];
                $process = proc_open(
                    $command,
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                    null,
                    null,
                    ['bypass_shell' => true]
                );

                if (!is_resource($process)) {
                    $this->results[$ip] = false;
                    continue;
                }

                $running[$ip] = ['process' => $process, 'pipes' => $pipes, 'startedAt' => microtime(true)];
            }

            foreach ($running as $ip => $job) {
                $status = proc_get_status($job['process']);
                $timedOut = microtime(true) - $job['startedAt'] >= self::PROCESS_TIMEOUT_SECONDS;
                if ($status['running'] && !$timedOut) {
                    continue;
                }

                if ($status['running']) {
                    proc_terminate($job['process']);
                }
                foreach ($job['pipes'] as $pipe) {
                    fclose($pipe);
                }
                $exitCode = proc_close($job['process']);
                $this->results[$ip] = !$timedOut && ($status['exitcode'] === 0 || $exitCode === 0);
                unset($running[$ip]);
            }

            if ($running) {
                usleep(10_000);
            }
        }

    }
}
