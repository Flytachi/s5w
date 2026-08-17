<?php

declare(strict_types=1);

namespace Main\Controllers\Api;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Positive;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\FullTokenMiddleware;
use Main\Http\BucketContext;
use Main\Request\PageRequest;
use Main\Request\ShareLinkRequest;
use Main\Service\ShareLinkService;
use Main\Support\Slug;

#[FullTokenMiddleware]
#[RequestMapping('v1')]
final class ShareLinkController extends Controller
{
    #[Autowired]
    private ShareLinkService $service;

    #[Autowired]
    private BucketContext $context;

    #[PostMapping('files/{slug}/link')]
    public function issue(
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        #[RequestJson, Valid] ShareLinkRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::created(
            $this->service->issue($this->context->bucketId(), $slug, $request, $this->baseUrl($http)),
        );
    }

    #[GetMapping('files/{slug}/links')]
    public function ofFile(
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->forFile($this->context->bucketId(), $slug, $this->baseUrl($http)),
        );
    }

    #[GetMapping('links')]
    public function list(
        #[RequestQuery, Valid] PageRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->getAll($this->context->bucketId(), $request, $this->baseUrl($http)),
        );
    }

    #[DeleteMapping('links/{id}')]
    public function revoke(
        #[PathVariable, Positive] int $id,
    ): ResponseEntity {
        $this->service->revoke($this->context->bucketId(), $id);
        return ResponseEntity::noContent();
    }

    #[DeleteMapping('links')]
    public function revokeAll(): ResponseEntity
    {
        return ResponseEntity::ok(['epoch' => $this->service->revokeAll($this->context->bucketId())]);
    }

    private function baseUrl(HttpRequest $http): string
    {
        return (string) env('PUBLIC_BASE_URL', $http->getBaseUrl());
    }
}
