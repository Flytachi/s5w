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
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\PutMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Request\BucketRequest;
use Main\Request\PageRequest;
use Main\Service\BucketService;

#[RequestMapping('admin/buckets')]
final class BucketController extends Controller
{
    #[Autowired]
    private BucketService $service;

    #[GetMapping]
    public function list(
        #[RequestQuery, Valid] PageRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->getAll($request));
    }

    #[PostMapping]
    public function create(
        #[RequestJson, Valid] BucketRequest $request,
    ): ResponseEntity {
        return ResponseEntity::created($this->service->create($request));
    }

    #[GetMapping('{id}')]
    public function get(
        #[PathVariable, Uuid] string $id,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->getOne($id));
    }

    #[PutMapping('{id}')]
    public function update(
        #[PathVariable, Uuid] string $id,
        #[RequestJson, Valid] BucketRequest $request,
    ): ResponseEntity {
        return ResponseEntity::ok($this->service->update($id, $request));
    }

    #[DeleteMapping('{id}')]
    public function delete(
        #[PathVariable, Uuid] string $id,
    ): ResponseEntity {
        $this->service->delete($id);
        return ResponseEntity::noContent();
    }
}
