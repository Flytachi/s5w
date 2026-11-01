<?php

declare(strict_types=1);

namespace Main\Controllers\Admin;

use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Request\Annotation\RequestJson;
use Flytachi\Winter\Kernel\Http\Request\Validation\Valid;
use Flytachi\Winter\Kernel\Http\Response\ResponseEntity;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\PostMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Controllers\Middlewares\AdminAuthMiddleware;
use Main\Controllers\Requests\LoginRequest;
use Main\Http\AdminCookie;
use Main\Http\AdminNetwork;
use Main\Http\Peer;
use Main\Services\AdminAuthService;

#[RequestMapping('admin/auth')]
final class AuthController extends Controller
{
    #[Autowired]
    private AdminAuthService $service;

    #[PostMapping('login')]
    public function login(
        #[RequestJson, Valid] LoginRequest $request,
        HttpRequest $http,
    ): ResponseEntity {
        AdminNetwork::guard($http);
        $session = $this->service->login($request, Peer::client($http));

        return ResponseEntity::ok(['expiresAt' => date('Y-m-d H:i:s P', $session['expiresAt'])])
            ->cookie(AdminCookie::issue($session['token']));
    }

    #[AdminAuthMiddleware]
    #[PostMapping('logout')]
    public function logout(): ResponseEntity
    {
        return ResponseEntity::ok(['ok' => true])
            ->cookie(AdminCookie::drop());
    }
}
