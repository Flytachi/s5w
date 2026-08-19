<?php

declare(strict_types=1);

namespace Main\Controllers\Admin;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestBody;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestHeader;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PatchMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AdminAuthMiddleware;
use Main\Request\UploadStartRequest;
use Main\Service\UploadService;

#[AdminAuthMiddleware]
#[RequestMapping('admin/buckets/{bucketId}/uploads')]
final class UploadController extends Controller
{
    #[Autowired]
    private UploadService $service;

    #[PostMapping]
    public function start(
        #[PathVariable, Uuid] string $bucketId,
        #[RequestJson, Valid] UploadStartRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::created(
            $this->service->create($bucketId, $request, $this->baseUrl($http)),
        );
    }

    #[GetMapping('{id}')]
    public function status(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Uuid] string $id,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->status($bucketId, $id));
    }

    #[PatchMapping('{id}')]
    public function append(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Uuid] string $id,
        #[RequestBody] string $chunk,
        #[RequestHeader('Upload-Offset')] ?string $offset = null,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->append(
            $bucketId,
            $id,
            $offset === null ? null : (int) $offset,
            $chunk,
        ));
    }

    #[PostMapping('{id}/complete')]
    public function complete(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Uuid] string $id,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::created(
            $this->service->complete($bucketId, $id, $this->baseUrl($http)),
        );
    }

    #[DeleteMapping('{id}')]
    public function abort(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Uuid] string $id,
    ): ResponseEntity {
        $this->service->abort($bucketId, $id);

        return ResponseEntity::noContent();
    }

    private function baseUrl(HttpRequest $http): string
    {
        return (string) env('PUBLIC_BASE_URL', $http->getBaseUrl());
    }
}
