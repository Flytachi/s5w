<?php

declare(strict_types=1);

namespace Main\Controllers\Delivery;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Response\ResponseStreamFile;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AccessTokenMiddleware;
use Main\Http\BucketContext;
use Main\Service\DeliveryService;
use Main\Support\Slug;

#[AccessTokenMiddleware]
#[RequestMapping('p')]
final class PrivateController extends Controller
{
    #[Autowired]
    private DeliveryService $service;

    #[Autowired]
    private BucketContext $context;

    #[GetMapping('{slug}')]
    public function download(
        #[PathVariable, Size(min: Slug::LENGTH, max: Slug::LENGTH)] string $slug,
        HttpRequest $http,
    ): ResponseStreamFile {
        return $this->service->private(
            $this->context->bucketId(),
            $slug,
            PublicController::isDownload($http),
        );
    }
}
