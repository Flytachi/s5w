<?php

declare(strict_types=1);

namespace Main\Controllers\Web;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\Kernel\Http\Response\ResponseException;
use Flytachi\Winter\Kernel\Http\Stereotype\Controller;
use Flytachi\Winter\Kernel\Route\Annotation\GetMapping;

/**
 * Короткие адреса панели: `/` и `/admin` ведут на `/admin/ui`.
 *
 * Сами страницы остаются под `/admin/ui` — `/admin/*` занят JSON-ручками
 * (`/admin/buckets`, `/admin/auth`), и посадить страницы на тот же префикс
 * значило бы отдавать по одному адресу то HTML, то JSON.
 */
final class EntryController extends Controller
{
    #[GetMapping]
    #[GetMapping('admin')]
    public function entry(): never
    {
        throw (new ResponseException('', HttpCode::FOUND))
            ->withHeader('Location', '/admin/ui');
    }
}
