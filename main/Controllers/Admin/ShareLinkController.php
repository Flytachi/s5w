<?php

declare(strict_types=1);

namespace Main\Controllers\Admin;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AdminAuthMiddleware;
use Main\Enum\LinkPurge;
use Main\Request\PageRequest;
use Main\Request\ShareLinkRequest;
use Main\Service\ShareLinkService;
use Main\Support\Slug;

#[AdminAuthMiddleware]
#[RequestMapping('admin/buckets/{bucketId}')]
final class ShareLinkController extends Controller
{
    #[Autowired]
    private ShareLinkService $service;

    #[PostMapping('files/{slug}/link')]
    public function issue(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        #[RequestJson, Valid] ShareLinkRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::created(
            $this->service->issue($bucketId, $slug, $request, $this->baseUrl($http)),
        );
    }

    #[GetMapping('files/{slug}/links')]
    public function ofFile(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->forFile($bucketId, $slug, $this->baseUrl($http)));
    }

    #[GetMapping('links')]
    public function list(
        #[PathVariable, Uuid] string $bucketId,
        #[RequestQuery, Valid] PageRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->getAll($bucketId, $request, $this->baseUrl($http)),
        );
    }

    #[DeleteMapping('links/purge/{state}')]
    public function purge(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable] LinkPurge $state,
    ): ResponseEntity {
        return ResponseEntity::ok(['removed' => $this->service->purge($bucketId, $state)]);
    }

    #[DeleteMapping('links/{id}')]
    public function revoke(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Positive] int $id,
    ): ResponseEntity {
        $this->service->revoke($bucketId, $id);
        return ResponseEntity::noContent();
    }

    #[DeleteMapping('links')]
    public function revokeAll(
        #[PathVariable, Uuid] string $bucketId,
    ): ResponseEntity {
        return ResponseEntity::ok(['epoch' => $this->service->revokeAll($bucketId)]);
    }

    private function baseUrl(HttpRequest $http): string
    {
        return (string) env('PUBLIC_BASE_URL', $http->getBaseUrl());
    }
}
