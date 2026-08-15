<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Cdo\Qb;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Unit\Pagination\WrapResult;
use Flytachi\Winter\Kernel\Unit\Wrapper;
use Main\Dto\AccessTokenRes;
use Main\Dto\AccessTokenSecretRes;
use Main\Entity\AccessToken;
use Main\Enum\TokenStatus;
use Main\Repository\AccessTokenRepository;
use Main\Request\AccessTokenRequest;
use Main\Request\PageRequest;
use Main\Support\TokenGenerator;

#[Singleton]
final class AccessTokenService
{
    #[Autowired]
    private AccessTokenRepository $repo;

    #[Autowired]
    private BucketService $buckets;

    public function getAll(string $bucketId, PageRequest $request): WrapResult
    {
        $where = [Qb::eq('bucket_id', $bucketId)];
        if ($request->search !== null && $request->search !== '') {
            $where[] = Qb::like('name', '%' . $request->search . '%');
        }

        return Wrapper::paginator(
            $this->repo->where(Qb::and(...$where))->orderBy('created_at DESC'),
            $request->limit,
            $request->page,
            mapper: fn(AccessToken $token) => AccessTokenRes::from($token),
        );
    }

    public function getOne(string $bucketId, int $id): AccessTokenRes
    {
        return AccessTokenRes::from($this->get($bucketId, $id));
    }

    /**
     * Всегда ищем парой (бакет, id): иначе админ одного бакета дотянулся бы до
     * чужого токена, просто подставив соседний id.
     */
    public function get(string $bucketId, int $id): AccessToken
    {
        $token = $this->repo->findBy(Qb::and(
            Qb::eq('id', $id),
            Qb::eq('bucket_id', $bucketId),
        ));
        if ($token === null) {
            ClientError::throw('Token not found', HttpCode::NOT_FOUND);
        }
        return $token;
    }

    public function create(string $bucketId, AccessTokenRequest $request): AccessTokenSecretRes
    {
        $this->buckets->get($bucketId); // 404, если бакета нет

        $generated = TokenGenerator::generate();

        $token = new AccessToken();
        $token->bucket_id = $bucketId;
        $token->hash = $generated['hash'];
        $token->name = $request->name;
        $token->status = TokenStatus::ACTIVE->value;
        $token->expires_at = $this->expiryFrom($request->expiresInDays);
        $token->created_at = date('Y-m-d H:i:s P');
        $token->id = $this->repo->insert($token);

        return new AccessTokenSecretRes($generated['token'], AccessTokenRes::from($token));
    }

    /**
     * Ротация: старый секрет умирает в момент записи нового хеша, строка
     * остаётся той же — имя, статус и срок переживают смену секрета.
     *
     * TODO (когда появится кэш аутентификации): сбросить запись по старому хешу,
     * иначе отозванный секрет доживёт в кэше до истечения TTL.
     */
    public function rotate(string $bucketId, int $id): AccessTokenSecretRes
    {
        $token = $this->get($bucketId, $id);
        $generated = TokenGenerator::generate();

        $token->hash = $generated['hash'];
        $this->repo->update(['hash' => $token->hash], Qb::eq('id', $token->id));

        return new AccessTokenSecretRes($generated['token'], AccessTokenRes::from($token));
    }

    public function changeStatus(string $bucketId, int $id, TokenStatus $status): AccessTokenRes
    {
        $token = $this->get($bucketId, $id);
        if ($token->status === $status->value) {
            ClientError::throw('Token already has this status', HttpCode::CONFLICT);
        }

        $token->status = $status->value;
        $this->repo->update(['status' => $token->status], Qb::eq('id', $token->id));

        return AccessTokenRes::from($token);
    }

    public function delete(string $bucketId, int $id): void
    {
        $token = $this->get($bucketId, $id);
        $this->repo->delete(Qb::eq('id', $token->id));
    }

    private function expiryFrom(?int $days): ?string
    {
        if ($days === null) {
            return null;
        }
        return (new \DateTimeImmutable())
            ->add(new \DateInterval("P{$days}D"))
            ->format('Y-m-d H:i:s P');
    }
}
