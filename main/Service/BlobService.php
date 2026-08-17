<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Main\Dto\StoredBlob;
use Main\Entity\Blob;
use Main\Repository\BlobRepository;
use Main\Repository\BucketRepository;
use Main\Storage\BlobStore;
use Main\Support\ContentType;
use Main\Support\Db;

#[Singleton]
final class BlobService
{
    #[Autowired]
    private BlobRepository $repo;

    #[Autowired]
    private BucketRepository $buckets;

    #[Autowired]
    private BlobStore $store;

    /**
     * @param string $srcPath временный файл; при успешной укладке он уезжает в хранилище
     * @param string|null $sourceName исходное имя — только как подсказка для типа
     */
    public function store(string $bucketId, string $srcPath, ?string $sourceName = null): StoredBlob
    {
        if (!is_file($srcPath)) {
            ClientError::throw('Upload source is missing', HttpCode::BAD_REQUEST);
        }

        $hash = hash_file('sha256', $srcPath);
        $size = (int) filesize($srcPath);
        $type = ContentType::detect($srcPath, $sourceName);

        $db = $this->repo->db();
        $owns = !$db->inTransaction();
        if ($owns) {
            $db->beginTransaction();
        }
        $written = false;

        try {
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $blob = $this->repo
                    ->where(Qb::and(Qb::eq('bucket_id', $bucketId), Qb::eq('hash', $hash)))
                    ->forBy('UPDATE')
                    ->find();

                if ($blob !== null) {
                    $blob->ref_count++;
                    $this->repo->update(['ref_count' => $blob->ref_count], Qb::eq('id', $blob->id));
                    if ($owns) {
                        $db->commit();
                    }
                    return new StoredBlob($blob, true, $type['mime'], $type['extension']);
                }

                $bucket = $this->buckets->where(Qb::eq('id', $bucketId))->forBy('UPDATE')->find();
                if ($bucket === null) {
                    ClientError::throw('Bucket not found', HttpCode::NOT_FOUND);
                }

                if ($bucket->used_bytes + $size > $bucket->quota_bytes) {
                    ClientError::throw(
                        "Quota exceeded: {$bucket->used_bytes} + {$size} > {$bucket->quota_bytes}",
                        HttpCode::REQUEST_ENTITY_TOO_LARGE,
                    );
                }

                if (!$written) {
                    $this->store->blobWrite($bucketId, $hash, $srcPath);
                    $written = true;
                }

                $blob = new Blob();
                $blob->bucket_id = $bucketId;
                $blob->hash = $hash;
                $blob->size_bytes = $size;
                $blob->ref_count = 1;
                $blob->created_at = date('Y-m-d H:i:s P');

                $db->exec('SAVEPOINT s5w_blob');
                try {
                    $blob->id = $this->repo->insert($blob);
                    $db->exec('RELEASE SAVEPOINT s5w_blob');
                } catch (\Throwable $e) {
                    if (!Db::isUniqueViolation($e)) {
                        throw $e;
                    }
                    $db->exec('ROLLBACK TO SAVEPOINT s5w_blob');
                    continue;
                }

                $this->buckets->update(
                    ['used_bytes' => $bucket->used_bytes + $size, 'updated_at' => date('Y-m-d H:i:s P')],
                    Qb::eq('id', $bucketId),
                );

                if ($owns) {
                    $db->commit();
                }

                return new StoredBlob($blob, false, $type['mime'], $type['extension']);
            }

            throw new \RuntimeException("Blob store gave up after a dedup race on {$hash}");
        } catch (\Throwable $e) {
            if ($owns && $db->inTransaction()) {
                $db->rollBack();
                if ($written) {
                    $this->store->blobDelete($bucketId, $hash);
                }
            }
            throw $e;
        }
    }

    /**
     * @return Blob|null осиротевший блоб, если сняли последнюю ссылку и вызов
     */
    public function release(string $bucketId, int $blobId): ?Blob
    {
        $db = $this->repo->db();
        $owns = !$db->inTransaction();
        if ($owns) {
            $db->beginTransaction();
        }
        $orphan = null;

        try {
            $blob = $this->repo
                ->where(Qb::and(Qb::eq('id', $blobId), Qb::eq('bucket_id', $bucketId)))
                ->forBy('UPDATE')
                ->find();
            if ($blob === null) {
                if ($owns) {
                    $db->commit();
                }
                return null;
            }

            if ($blob->ref_count > 1) {
                $this->repo->update(['ref_count' => $blob->ref_count - 1], Qb::eq('id', $blob->id));
            } else {
                $this->repo->delete(Qb::eq('id', $blob->id));

                $bucket = $this->buckets->where(Qb::eq('id', $bucketId))->forBy('UPDATE')->find();
                if ($bucket !== null) {
                    $this->buckets->update(
                        [
                            'used_bytes' => max(0, $bucket->used_bytes - $blob->size_bytes),
                            'updated_at' => date('Y-m-d H:i:s P'),
                        ],
                        Qb::eq('id', $bucketId),
                    );
                }
                $orphan = $blob;
            }

            if ($owns) {
                $db->commit();
            }
        } catch (\Throwable $e) {
            if ($owns && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($orphan !== null && $owns) {
            $this->store->blobDelete($orphan->bucket_id, $orphan->hash);
            return null;
        }

        return $orphan;
    }
}
