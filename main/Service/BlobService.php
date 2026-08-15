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

        $db = $this->repo->db();
        // Транзакцию открываем, только если её нет: загрузка файла оборачивает
        // блоб и строку файла в одну свою, чтобы они появлялись вместе.
        $owns = !$db->inTransaction();
        if ($owns) {
            $db->beginTransaction();
        }
        $written = false;

        try {
            // Две попытки: на второй мы уже точно видим чужую строку (INSERT,
            // получивший 23505, ждал коммита победителя — значит, она видна).
            for ($attempt = 0; $attempt < 2; $attempt++) {
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
                    if ($owns) {
                        $db->commit();
                    }
                    // Файл (если мы его успели записать на первой попытке) не
                    // трогаем: путь content-addressed, там те же байты, и теперь
                    // они принадлежат победившей строке.
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

                // Байты пишем до коммита: файл без строки — мусор, который
                // подберёт уборщик, а строка без файла — 500 на каждой отдаче.
                if (!$written) {
                    $this->store->blobWrite($bucketId, $hash, $srcPath);
                    $written = true;
                }

                $blob = new Blob();
                $blob->bucket_id = $bucketId;
                $blob->hash = $hash;
                $blob->size_bytes = $size;
                $blob->mime_type = $type['mime'];
                $blob->extension = $type['extension'];
                $blob->ref_count = 1;
                $blob->created_at = date('Y-m-d H:i:s P');

                // Гонка двух одинаковых загрузок: FOR UPDATE не запирает ещё не
                // существующую строку, поэтому обе доходят до INSERT и одна
                // получает 23505. Savepoint нужен, чтобы этот отказ не убил всю
                // транзакцию — в том числе внешнюю, чужую.
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

                return new StoredBlob($blob, deduplicated: false);
            }

            throw new \RuntimeException("Blob store gave up after a dedup race on {$hash}");
        } catch (\Throwable $e) {
            if ($owns && $db->inTransaction()) {
                $db->rollBack();
                if ($written) {
                    $this->store->blobDelete($bucketId, $hash);
                }
            }
            // Во внешней транзакции файл убирает тот, кто её откатывает: только
            // он знает, дошло ли дело до коммита (см. StoredBlob::$written).
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
     *
     * @return Blob|null осиротевший блоб, если сняли последнюю ссылку и вызов
     *   идёт внутри чужой транзакции: удалить его файл должен тот, кто её
     *   коммитит. При собственной транзакции файл удаляется здесь и возвращается
     *   null — вызывающему делать нечего.
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
                return null; // уже освобождён — release идемпотентен
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

        // Внутри внешней транзакции файл ещё не наш, чтобы его удалять: она
        // может откатиться, и строка блоба вернётся к жизни.
        if ($orphan !== null && $owns) {
            $this->store->blobDelete($orphan->bucket_id, $orphan->hash);
            return null;
        }

        return $orphan;
    }
}
