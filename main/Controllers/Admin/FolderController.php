<?php

declare(strict_types=1);

namespace Main\Controllers\Admin;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestQuery;
use Flytachi\Winter\Kernel\Http\Request\Validation\Uuid;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\DeleteMapping;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PatchMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PutMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AdminAuthMiddleware;
use Main\Request\CachePolicyRequest;
use Main\Request\FolderRequest;
use Main\Request\PageRequest;
use Main\Service\FolderService;

#[AdminAuthMiddleware]
#[RequestMapping('admin/buckets/{bucketId}/folders')]
final class FolderController extends Controller
{
    #[Autowired]
    private FolderService $service;

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
        #[RequestJson, Valid] FolderRequest $request,
    ): ResponseEntity {
        return ResponseEntity::created($this->service->create($bucketId, $request));
    }

    #[GetMapping('{name}')]
    public function get(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable] string $name,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->getOne($bucketId, self::name($name)));
    }

    #[PutMapping('{name}')]
    public function update(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable] string $name,
        #[RequestJson, Valid] FolderRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->update($bucketId, self::name($name), $request));
    }

    #[PatchMapping('{name}/cache')]
    public function setCachePolicy(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable] string $name,
        #[RequestJson, Valid] CachePolicyRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->setCachePolicy($bucketId, self::name($name), $request));
    }

    #[DeleteMapping('{name}')]
    public function delete(
        #[PathVariable, Uuid] string $bucketId,
        #[PathVariable] string $name,
    ): ResponseEntity {
        $this->service->delete($bucketId, self::name($name));
        return ResponseEntity::noContent();
    }

    private static function name(string $raw): string
    {
        return rawurldecode($raw);
    }
}
