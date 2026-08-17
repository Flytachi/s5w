<?php

declare(strict_types=1);

namespace Main\Controllers\Api;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AccessTokenMiddleware;
use Main\Http\BucketContext;
use Main\Service\AccessTokenService;

#[AccessTokenMiddleware]
#[RequestMapping('v1')]
final class CheckController extends Controller
{
    #[Autowired]
    private AccessTokenService $service;

    #[Autowired]
    private BucketContext $context;

    #[GetMapping('check')]
    public function check(): ResponseEntity
    {
        return ResponseEntity::ok($this->service->describe($this->context->token()));
    }
}
