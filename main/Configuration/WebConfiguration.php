<?php

namespace Main\Configuration;

use Flytachi\Winter\Kernel\App\ApplicationArguments;
use Flytachi\Winter\Kernel\App\Config\ServerSettings;
use Flytachi\Winter\Kernel\App\Config\WebConfigurerAdapter;
use Main\Service\UploadService;

class WebConfiguration extends WebConfigurerAdapter
{
    /**
     * Потолок одного тела запроса.
     *
     * Настройкой не выносится намеренно: размер файла в него больше не упирается —
     * тяжёлое идёт кусками через {@see UploadService}, и поднимать этот потолок ради
     * больших файлов не нужно. А цена у него памятью: Swoole собирает тело в куче
     * воркера целиком, примерно вдвое от размера, и лимит делится между всеми
     * запросами в работе. Замерено: 190 МиБ одним телом — 384 МБ кучи.
     *
     * Обязан быть больше {@see UploadService::CHUNK_MAX}, иначе сервер обещает кусок,
     * который сам же не примет.
     */
    private const int MAX_BODY = 32 * 1_048_576;

    public function configureServer(ServerSettings $server, ApplicationArguments $args): void
    {
        $server->maxRequestSize(self::MAX_BODY);

        $server->staticPath('resources/static')
            ->set('static_handler_locations', ['/assets', '/favicon.ico'])
            ->set('http_parse_cookie', false);
    }
}
