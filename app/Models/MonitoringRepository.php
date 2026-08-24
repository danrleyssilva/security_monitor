<?php

declare(strict_types=1);

namespace App\Models;

use PDO;
use RuntimeException;

final class MonitoringRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return list<array{id: mixed, ip: mixed, name: mixed}> */
    public function getNvrs(): array
    {
        $nvrColumns = $this->getColumns('nvrs');
        $nvrId = $this->findColumn($nvrColumns, ['id_nvr', 'nvr_id', 'id']);
        $nvrIp = $this->findColumn($nvrColumns, ['ip', 'ip_address', 'endereco_ip', 'host', 'hostname']);
        $nvrName = $this->findColumn($nvrColumns, ['dispositivo', 'nome', 'name', 'descricao', 'description', 'titulo']);

        if (!$nvrId || !$nvrIp) {
            throw new RuntimeException('Nao foi possivel identificar as colunas de ID/IP da tabela nvrs.');
        }

        return $this->pdo->query(sprintf(
            'SELECT `%s` AS id, `%s` AS ip, %s AS name FROM `nvrs` ORDER BY `%s`',
            $nvrId,
            $nvrIp,
            $nvrName ? '`' . $nvrName . '`' : 'NULL',
            $nvrName ?: $nvrId
        ))->fetchAll();
    }

    /** @return list<array{ip: mixed}> */
    public function getCamerasByNvr(mixed $nvrId): array
    {
        $columns = $this->getColumns('cameras');
        $cameraNvrId = $this->findColumn($columns, ['nvr_id', 'id_nvr', 'nvr', 'nvrid']);
        $cameraIp = $this->findColumn($columns, ['ip', 'ip_address', 'endereco_ip', 'host', 'hostname']);
        if (!$cameraNvrId || !$cameraIp) {
            throw new RuntimeException('Nao foi possivel identificar as colunas de ID/IP da tabela cameras.');
        }

        $statement = $this->pdo->prepare(sprintf(
            'SELECT `%s` AS ip FROM `cameras` WHERE `%s` = :nvr_id ORDER BY `%s`',
            $cameraIp,
            $cameraNvrId,
            $cameraIp
        ));
        $statement->execute(['nvr_id' => $nvrId]);

        return $statement->fetchAll();
    }

    /** @return array<string, int> */
    public function getFailureCounts(): array
    {
        $this->createFailureTable();
        return $this->pdo->query('SELECT `camera_key`, `failed_attempts` FROM `camera_ping_failures`')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function saveFailure(string $cameraKey, int $attempts): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO `camera_ping_failures` (`camera_key`, `failed_attempts`, `last_checked`)
             VALUES (:camera_key, :failed_attempts, NOW())
             ON DUPLICATE KEY UPDATE `failed_attempts` = VALUES(`failed_attempts`), `last_checked` = NOW()'
        );
        $statement->execute(['camera_key' => $cameraKey, 'failed_attempts' => $attempts]);
    }

    public function clearFailure(string $cameraKey): void
    {
        $statement = $this->pdo->prepare('DELETE FROM `camera_ping_failures` WHERE `camera_key` = :camera_key');
        $statement->execute(['camera_key' => $cameraKey]);
    }

    /** @return list<string> */
    private function getColumns(string $table): array
    {
        return array_column($this->pdo->query('DESCRIBE `' . $table . '`')->fetchAll(), 'Field');
    }

    /** @param list<string> $columns @param list<string> $candidates */
    private function findColumn(array $columns, array $candidates): ?string
    {
        $lookup = array_combine(array_map('strtolower', $columns), $columns);
        foreach ($candidates as $candidate) {
            if (isset($lookup[$candidate])) {
                return $lookup[$candidate];
            }
        }

        return null;
    }

    private function createFailureTable(): void
    {
        $this->pdo->exec(
            'CREATE TABLE IF NOT EXISTS `camera_ping_failures` (
                `camera_key` VARCHAR(191) NOT NULL,
                `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
                `last_checked` DATETIME NOT NULL,
                PRIMARY KEY (`camera_key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}
