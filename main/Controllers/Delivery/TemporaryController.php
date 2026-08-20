<?php

declare(strict_types=1);

namespace Main\Controllers\Delivery;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Request\Annotation\PathVariable;
use Flytachi\Winter\Kernel\Http\Request\Validation\Size;
use Flytachi\Winter\Kernel\Http\Response\ResponseStreamFile;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Service\DeliveryService;

#[RequestMapping('t')]
final class TemporaryController extends Controller
{
    #[Autowired]
    private DeliveryService $service;

    #[GetMapping('{token}')]
    public function download(
        #[PathVariable, Size(min: 40, max: 128)] string $token,
    ): ResponseStreamFile {
        return $this->service->temporary($token);
    }
}
