<?php

declare(strict_types=1);

namespace Main\Controllers\Admin;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestFile;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestForm;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PutMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Request\FileListRequest;
use Main\Request\FilePlacementRequest;
use Main\Request\FileUploadRequest;
use Main\Service\FileService;
use Main\Support\Slug;

/**
 * TODO: закрыть AdminJwtMiddleware (docs/plan.md §8.8).
 */
#[RequestMapping('admin/buckets/{bucketId}/files')]
final class FileController extends Controller
{
    #[Autowired]
    private FileService $service;

    #[GetMapping]
    public function list(
        #[PathVariable, Uuid] string $bucketId,
        #[RequestQuery, Valid] FileListRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->getAll($bucketId, $request, $this->baseUrl($http)),
        );
    }

    #[PostMapping]
    public function upload(
        #[PathVariable, Uuid] string $bucketId,
        #[RequestFile('file')] array $file,
        #[RequestForm, Valid] FileUploadRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::created(
            $this->service->upload($bucketId, $file, $request, $this->baseUrl($http)),
        );
    }

    #[GetMapping('{slug}')]
    public function get(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->getOne($bucketId, $slug, $this->baseUrl($http)),
        );
    }

    #[PutMapping('{slug}')]
    public function update(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        #[RequestJson, Valid] FilePlacementRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->update($bucketId, $slug, $request, $this->baseUrl($http)),
        );
    }

    #[DeleteMapping('{slug}')]
    public function delete(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
    ): ResponseEntity {
        $this->service->delete($bucketId, $slug);
        return ResponseEntity::noContent();
    }

    private function baseUrl(HttpRequest $http): string
    {
        return (string) env('PUBLIC_BASE_URL', $http->getBaseUrl());
    }
}
