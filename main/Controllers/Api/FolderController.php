<?php

declare(strict_types=1);

namespace Main\Controllers\Api;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
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
use Main\Request\FolderRequest;
use Main\Request\PageRequest;
use Main\Service\FolderService;

#[FullTokenMiddleware]
#[RequestMapping('v1/folders')]
final class FolderController extends Controller
{
    #[Autowired]
    private FolderService $service;

    #[Autowired]
    private BucketContext $context;

    #[GetMapping]
    public function list(
        #[RequestQuery, Valid] PageRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->getAll($this->context->bucketId(), $request));
    }

    #[PostMapping]
    public function create(
        #[RequestJson, Valid] FolderRequest $request,
    ): ResponseEntity {
        return ResponseEntity::created($this->service->create($this->context->bucketId(), $request));
    }

    #[GetMapping('{name}')]
    public function get(
        #[PathVariable] string $name,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->getOne($this->context->bucketId(), self::name($name)));
    }

    #[PutMapping('{name}')]
    public function update(
        #[PathVariable] string $name,
        #[RequestJson, Valid] FolderRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->update($this->context->bucketId(), self::name($name), $request));
    }

    #[DeleteMapping('{name}')]
    public function delete(
        #[PathVariable] string $name,
    ): ResponseEntity {
        $this->service->delete($this->context->bucketId(), self::name($name));
        return ResponseEntity::noContent();
    }

    private static function name(string $raw): string
    {
        return rawurldecode($raw);
    }
}
