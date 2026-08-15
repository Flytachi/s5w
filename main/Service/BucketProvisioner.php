<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Concurrent\Async\Async;
use Main\Enum\BucketStatus;
use Main\Repository\BucketRepository;
use Main\Storage\BlobStore;
use Psr\Log\LoggerInterface;

/**
 * Долгая часть жизненного цикла бакета: каталог в хранилище.
 *
 * Оба метода #[Async] — вызов возвращается сразу, тело уходит на исполнитель
 * (корутина под Swoole, отложенная задача в CLI). Отсюда требования к классу:
 * он не final, методы не final и не static, возвращают void — прокси
 * генерируется наследником, иначе перехватывать нечего.
 *
 * Обе операции идемпотентны: повторный запуск из любого статуса доводит
 * бакет до нужного состояния и ничего не ломает.
 */
#[Singleton]
class BucketProvisioner
{
    #[Autowired]
    private BucketRepository $repo;

    #[Autowired]
    private BlobStore $store;

    /** Контейнер отдаёт логгер, названный по классу-потребителю. */
    #[Autowired]
    private LoggerInterface $log;

    /**
     * CREATED → PENDING → ACTIVE. Сбой оставляет INACTIVE: бакет виден в панели,
     * но не обслуживает загрузки — состояние, из которого можно повторить.
     */
    #[Async]
    public function provision(string $bucketId): void
    {
        if ($this->repo->findById($bucketId) === null) {
            return; // удалили, пока задача ждала очереди
        }

        $this->setStatus($bucketId, BucketStatus::PENDING);

        try {
            $this->store->createBucketDir($bucketId);
            $this->setStatus($bucketId, BucketStatus::ACTIVE);
            $this->log->info("Bucket {$bucketId} provisioned");
        } catch (\Throwable $e) {
            $this->setStatus($bucketId, BucketStatus::INACTIVE);
            $this->log->error("Bucket {$bucketId} provisioning failed: {$e->getMessage()}");
        }
    }

    /**
     * Порядок намеренный: сначала строка, потом каталог.
     *
     * Строку уносит CASCADE вместе с папками, файлами, блобами и токенами —
     * после коммита база консистентна. Если процесс умрёт на удалении каталога,
     * на диске останется сирота: её подберёт будущий OrphanDirSweeper. Обратный
     * порядок оставил бы живую строку с исчезнувшими файлами — то есть 500 на
     * каждой отдаче вместо мусора на диске.
     */
    #[Async]
    public function purge(string $bucketId): void
    {
        try {
            $this->repo->delete(Qb::eq('id', $bucketId));
            $this->store->removeBucketDir($bucketId);
            $this->log->info("Bucket {$bucketId} purged");
        } catch (\Throwable $e) {
            $this->log->error("Bucket {$bucketId} purge failed: {$e->getMessage()}");
        }
    }

    private function setStatus(string $bucketId, BucketStatus $status): void
    {
        $this->repo->update(
            ['status' => $status->value, 'updated_at' => date('Y-m-d H:i:s P')],
            Qb::eq('id', $bucketId),
        );
    }
}
