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
     * Кладёт содержимое временного файла в бакет.
     *
     * Тот же контент второй раз не пишется и квоту не занимает: находим блоб по
     * (bucket, sha256) и просто увеличиваем счётчик ссылок.
     *
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

        for ($attempt = 0; ; $attempt++) {
            try {
                return $this->attemptStore($bucketId, $srcPath, $hash, $size, $type);
            } catch (\Throwable $e) {
                if ($attempt === 0 && Db::isUniqueViolation($e)) {
                    continue;
                }
                throw $e;
            }
        }
    }

    /**
     * @param array{mime: string, extension: string} $type
     */
    private function attemptStore(
        string $bucketId,
        string $srcPath,
        string $hash,
        int $size,
        array $type,
    ): StoredBlob {
        $db = $this->repo->db();
        $db->beginTransaction();
        $written = false;

        try {
            // FOR UPDATE держит существующий блоб от параллельного release:
            // иначе он мог бы обнулить ref_count и удалить файл ровно между
            // нашим SELECT и нашим UPDATE.
            $blob = $this->repo
                ->where(Qb::and(Qb::eq('bucket_id', $bucketId), Qb::eq('hash', $hash)))
                ->forBy('UPDATE')
                ->find();

            if ($blob !== null) {
                $blob->ref_count++;
                $this->repo->update(['ref_count' => $blob->ref_count], Qb::eq('id', $blob->id));
                $db->commit();

                return new StoredBlob($blob, deduplicated: true);
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

            // Байты пишем до коммита: файл без строки — мусор, который подберёт
            // уборщик, а строка без файла — 500 на каждой отдаче.
            $this->store->blobWrite($bucketId, $hash, $srcPath);
            $written = true;

            $blob = new Blob();
            $blob->bucket_id = $bucketId;
            $blob->hash = $hash;
            $blob->size_bytes = $size;
            $blob->mime_type = $type['mime'];
            $blob->extension = $type['extension'];
            $blob->ref_count = 1;
            $blob->created_at = date('Y-m-d H:i:s P');
            $blob->id = $this->repo->insert($blob);

            $this->buckets->update(
                ['used_bytes' => $bucket->used_bytes + $size, 'updated_at' => date('Y-m-d H:i:s P')],
                Qb::eq('id', $bucketId),
            );

            $db->commit();

            return new StoredBlob($blob, deduplicated: false);
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            // Файл удаляем, только если он остался ничей. При 23505 путь занят
            // победившей транзакцией — там ровно те же байты, и стереть их
            // значило бы оставить чужую строку без содержимого.
            if ($written && !Db::isUniqueViolation($e)) {
                $this->store->blobDelete($bucketId, $hash);
            }
            throw $e;
        }
    }

    /**
     * Снимает одну ссылку. Последняя уносит и строку, и файл, и занятое место.
     *
     * unlink делается после коммита намеренно: откат транзакции не должен
     * стоить нам файла, на который кто-то ещё ссылается. Обратная ошибка —
     * файл-сирота при падении между коммитом и unlink — стоит места, а не
     * данных, и подбирается уборщиком.
     */
    public function release(string $bucketId, int $blobId): void
    {
        $db = $this->repo->db();
        $db->beginTransaction();
        $orphan = null;

        try {
            $blob = $this->repo
                ->where(Qb::and(Qb::eq('id', $blobId), Qb::eq('bucket_id', $bucketId)))
                ->forBy('UPDATE')
                ->find();
            if ($blob === null) {
                $db->commit();
                return; // уже освобождён — release идемпотентен
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

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($orphan !== null) {
            $this->store->blobDelete($orphan->bucket_id, $orphan->hash);
        }
    }
}
