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

#[Singleton]
class BucketProvisioner
{
    #[Autowired]
    private BucketRepository $repo;

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private LoggerInterface $log;

    #[Async]
    public function provision(string $bucketId): void
    {
        if ($this->repo->findById($bucketId) === null) {
            return;
        }

        $this->setStatus($bucketId, BucketStatus::PENDING);

        try {
            $this->store->createBucketDir($bucketId);
            $this->setStatus($bucketId, BucketStatus::ACTIVE);
            $this->log->info("bucket {$bucketId} provisioned");
        } catch (\Throwable $e) {
            $this->setStatus($bucketId, BucketStatus::INACTIVE);
            $this->log->error("bucket {$bucketId} provisioning failed: {$e->getMessage()}");
        }
    }

    #[Async]
    public function purge(string $bucketId): void
    {
        try {
            $this->repo->delete(Qb::eq('id', $bucketId));
            $this->store->removeBucketDir($bucketId);
            $this->log->info("bucket {$bucketId} purged");
        } catch (\Throwable $e) {
            $this->log->error("bucket {$bucketId} purge failed: {$e->getMessage()}");
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
