<?php

declare(strict_types=1);

namespace Main\Controllers\Admin;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PatchMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Request\AccessTokenRequest;
use Main\Request\PageRequest;
use Main\Request\TokenStatusRequest;
use Main\Service\AccessTokenService;

/**
 * TODO: закрыть AdminJwtMiddleware (docs/plan.md §8.8).
 */
#[RequestMapping('admin/buckets/{bucketId}/tokens')]
final class AccessTokenController extends Controller
{
    #[Autowired]
    private AccessTokenService $service;

    #[GetMapping]
    public function list(
        #[PathVariable, Uuid] string $bucketId,
        #[RequestQuery, Valid] PageRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->getAll($bucketId, $request));
    }

    #[PostMapping]
    public function create(
        #[PathVariable, Uuid] string $bucketId,
        #[RequestJson, Valid] AccessTokenRequest $request,
    ): ResponseEntity {
        return ResponseEntity::created($this->service->create($bucketId, $request));
    }

    #[GetMapping('{id}')]
    public function get(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Positive] int $id,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->getOne($bucketId, $id));
    }

    #[PostMapping('{id}/rotate')]
    public function rotate(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Positive] int $id,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->rotate($bucketId, $id));
    }

    #[PatchMapping('{id}/status')]
    public function changeStatus(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Positive] int $id,
        #[RequestJson, Valid] TokenStatusRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->changeStatus($bucketId, $id, $request->status));
    }

    #[DeleteMapping('{id}')]
    public function delete(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Positive] int $id,
    ): ResponseEntity {
        $this->service->delete($bucketId, $id);
        return ResponseEntity::noContent();
    }
}
