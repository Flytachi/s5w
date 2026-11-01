<?php

declare(strict_types=1);

namespace Main\Sweepers\Orphans;

use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Main\Entities\Blob;
use Main\Repositories\BlobRepository;
use Main\Repositories\BucketRepository;
use Main\Repositories\FileEntryRepository;
use Main\Storage\BlobStore;
use Main\Sweepers\Sweeper;
use Psr\Log\LoggerInterface;

#[Singleton]
final class OrphanBlobsSweeper implements Sweeper
{
    private const int BATCH = 500;
    private const int MAX_BATCHES = 20;

    #[Autowired]
    private BlobRepository $repo;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private FileEntryRepository $files;

    #[Autowired]
    private BlobStore $store;

    #[Autowired]
    private LoggerInterface $log;

    public function sweep(bool $force = false): int
    {
        $removed = 0;

        for ($pass = 0; $pass < self::MAX_BATCHES; $pass++) {
            $orphans = $this->repo
                ->where(Qb::raw(sprintf(
                    "created_at <= now() - interval '1 hour'"
                    . ' AND NOT EXISTS (SELECT 1 FROM %s f WHERE f.blob_id = %s.id)',
                    $this->files->originTable(),
                    $this->repo->originTable(),
                )))
                ->orderBy('id')
                ->limit(self::BATCH)
                ->findAll();

            if ($orphans === []) {
                break;
            }

            foreach ($orphans as $blob) {
                /** @var Blob $blob */
                try {
                    $this->drop($blob);
                    $removed++;
                } catch (\Throwable $e) {
                    $this->log->error("failed to remove unreferenced blob {$blob->id}: {$e->getMessage()}");
                }
            }

            if (count($orphans) < self::BATCH) {
                break;
            }
        }

        return $removed;
    }

    private function drop(Blob $blob): void
    {
        $this->repo->db()->transaction(function () use ($blob): void {
            $this->repo->delete(Qb::eq('id', $blob->id));

            $bucket = $this->buckets->where(Qb::eq('id', $blob->bucket_id))->forBy('UPDATE')->find();
            if ($bucket !== null) {
                $this->buckets->update(
                    [
                        'used_bytes' => max(0, $bucket->used_bytes - $blob->size_bytes),
                        'updated_at' => date('Y-m-d H:i:s P'),
                    ],
                    Qb::eq('id', $blob->bucket_id),
                );
            }
        });

        $this->store->blobDelete($blob->bucket_id, $blob->hash);
    }
}
