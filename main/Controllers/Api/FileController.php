<?php

declare(strict_types=1);

namespace Main\Controllers\Api;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestFile;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestForm;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PutMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\FullTokenMiddleware;
use Main\Http\BucketContext;
use Main\Request\FileListRequest;
use Main\Request\FilePlacementRequest;
use Main\Request\FileUploadRequest;
use Main\Service\FileService;
use Main\Support\Slug;

#[FullTokenMiddleware]
#[RequestMapping('v1/files')]
final class FileController extends Controller
{
    #[Autowired]
    private FileService $service;

    #[Autowired]
    private BucketContext $context;

    #[GetMapping]
    public function list(
        #[RequestQuery, Valid] FileListRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->getAll($this->context->bucketId(), $request, $this->baseUrl($http)),
        );
    }

    #[PostMapping]
    public function upload(
        #[RequestFile('file')] array $file,
        #[RequestForm, Valid] FileUploadRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::created(
            $this->service->upload($this->context->bucketId(), $file, $request, $this->baseUrl($http)),
        );
    }

    #[GetMapping('{slug}')]
    public function get(
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->getOne($this->context->bucketId(), $slug, $this->baseUrl($http)),
        );
    }

    #[PutMapping('{slug}')]
    public function update(
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        #[RequestJson, Valid] FilePlacementRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        return ResponseEntity::ok(
            $this->service->update($this->context->bucketId(), $slug, $request, $this->baseUrl($http)),
        );
    }

    #[DeleteMapping('{slug}')]
    public function delete(
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
    ): ResponseEntity {
        $this->service->delete($this->context->bucketId(), $slug);
        return ResponseEntity::noContent();
    }

    private function baseUrl(HttpRequest $http): string
    {
        $toEnv = (string) env('PUBLIC_BASE_URL', '');
        return empty($toEnv) ? $http->getBaseUrl() : $toEnv;
    }
}
