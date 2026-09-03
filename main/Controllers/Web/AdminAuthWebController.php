<?php

declare(strict_types=1);

namespace Main\Controllers\Web;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\Kernel\Http\Contracts\HttpRequest;
use Flytachi\Winter\Kernel\Http\Response\ResponseException;
use Flytachi\Winter\Kernel\Http\Response\ResponseView;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Http\Header;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;
use Flytachi\Winter\Kernel\Route\Annotation\RequestMapping;
use Main\Http\AdminCookie;
use Main\Service\AdminAuthService;

#[RequestMapping('admin/ui')]
final class AdminAuthWebController extends Controller
{
    #[Autowired]
    private AdminAuthService $auth;

    #[GetMapping('login')]
    public function login(HttpRequest $request): ResponseView
    {
        $next = $this->next($request);
        if ($this->auth->verify(Header::getBearerToken() ?? AdminCookie::read())) {
            throw (new ResponseException('', HttpCode::FOUND))->withHeader('Location', $next);
        }

        return ResponseView::view('admin/login', [
            'next' => $next,
            'locked' => !$this->auth->isConfigured(),
        ]);
    }

    private function next(HttpRequest $request): string
    {
        $next = (string) ($request->getQueryParams()['next'] ?? '');

        return str_starts_with($next, '/admin/ui') && !str_starts_with($next, '/admin/ui/login')
            ? $next
            : '/admin/ui';
    }
}
