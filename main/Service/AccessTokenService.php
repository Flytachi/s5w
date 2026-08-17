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
use Main\Dto\CheckRes;
use Main\Dto\TokenCounts;
use Main\Entity\AccessToken;
use Main\Enum\TokenAccess;
use Main\Enum\TokenStatus;
use Main\Repository\AccessTokenRepository;
use Main\Request\AccessTokenRequest;
use Main\Request\PageRequest;
use Main\Request\TokenListRequest;
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

    public function describe(AccessToken $token): CheckRes
    {
        return CheckRes::from($token, $this->buckets->get($token->bucket_id));
    }

    public function panelPage(string $bucketId, TokenListRequest $request): WrapResult
    {
        $where = [Qb::eq('bucket_id', $bucketId)];
        if ($request->search !== null && $request->search !== '') {
            $where[] = Qb::like('name', '%' . $request->search . '%');
        }

        return Wrapper::paginator(
            $this->repo->where(Qb::and(...$where))->orderBy($this->orderBy($request)),
            $request->limit,
            $request->page,
            mapper: fn(AccessToken $token) => AccessTokenRes::from($token),
        );
    }

    /** @return array{total: int, active: int, full: int, inactive: int, expired: int} */
    public function counts(string $bucketId): array
    {
        $active = sprintf(
            'status = %d AND (expires_at IS NULL OR expires_at > now())',
            TokenStatus::ACTIVE->value,
        );

        $row = $this->repo
            ->select(sprintf(
                'count(*) AS total,'
                . ' count(*) FILTER (WHERE %1$s) AS active,'
                . ' count(*) FILTER (WHERE %1$s AND access = %2$d) AS full,'
                . ' count(*) FILTER (WHERE status <> %3$d) AS inactive,'
                . ' count(*) FILTER (WHERE status = %3$d AND expires_at <= now()) AS expired',
                $active,
                TokenAccess::FULL->value,
                TokenStatus::ACTIVE->value,
            ))
            ->findBy(Qb::eq('bucket_id', $bucketId), TokenCounts::class) ?? new TokenCounts();

        return [
            'total' => $row->total,
            'active' => $row->active,
            'full' => $row->full,
            'inactive' => $row->inactive,
            'expired' => $row->expired,
        ];
    }

    private function orderBy(TokenListRequest $request): string
    {
        $dir = $request->dir === 'asc' ? 'ASC' : 'DESC';

        return match ($request->sort) {
            'name' => "name {$dir}",
            'access' => "access {$dir}, name ASC",
            'state' => 'CASE WHEN status = 0 THEN 0'
                . ' WHEN expires_at IS NOT NULL AND expires_at <= now() THEN 1'
                . " ELSE 2 END {$dir}, name ASC",
            'used' => "last_used_at {$dir} NULLS LAST",
            default => "created_at {$dir}, id {$dir}",
        };
    }

    public function getOne(string $bucketId, int $id): AccessTokenRes
    {
        return AccessTokenRes::from($this->get($bucketId, $id));
    }

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
        $this->buckets->get($bucketId);

        $generated = TokenGenerator::generate();

        $token = new AccessToken();
        $token->bucket_id = $bucketId;
        $token->hash = $generated['hash'];
        $token->tail = $generated['tail'];
        $token->name = $request->name;
        $token->access = $request->access->value;
        $token->status = TokenStatus::ACTIVE->value;
        $token->expires_at = $this->expiryFrom($request->expiresInDays);
        $token->created_at = date('Y-m-d H:i:s P');
        $token->id = $this->repo->insert($token);

        return new AccessTokenSecretRes($generated['token'], AccessTokenRes::from($token));
    }

    public function rotate(string $bucketId, int $id): AccessTokenSecretRes
    {
        $token = $this->get($bucketId, $id);
        $generated = TokenGenerator::generate();

        $token->hash = $generated['hash'];
        $token->tail = $generated['tail'];
        $this->repo->update(
            ['hash' => $token->hash, 'tail' => $token->tail],
            Qb::eq('id', $token->id),
        );

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
