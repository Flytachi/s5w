<?php

namespace Main\Configuration;

use Flytachi\Winter\Kernel\App\ApplicationArguments;
use Flytachi\Winter\Kernel\App\Config\Profile;
use Flytachi\Winter\Kernel\App\Config\ServerSettings;
use Flytachi\Winter\Kernel\App\Config\WebConfigurerAdapter;
use Main\Cacheable\CacheRegistry;
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
        // Общая память под кэши отводится здесь, потому что это последняя точка перед
        // запуском сервера: Swoole\Table, созданная после форка, достаётся одному
        // воркеру, и отзыв токена в нём до остальных не дошёл бы.
        CacheRegistry::boot();

        // Запрос здесь тяжёлый: кусок загрузки — это мегабайты в куче, обработка
        // картинки — ещё столько же. Профиль отвечает ровно на этот вопрос и уже из
        // ответа выводит потолок одновременных запросов, размер пула соединений и то,
        // когда воркер отдаёт память системе. Потолок памяти воркера тут не задаётся:
        // он приходит из docker/php-memory.ini, и профиль считает от него.
        $server->profile(Profile::Stable)
            ->maxRequestSize(self::MAX_BODY);

        // Разбор кук ядро делает само — одинаково под Swoole и FPM, — так что
        // встроенный в Swoole разбор только дублировал бы работу.
        $server->staticPath('resources/static')
            ->set('static_handler_locations', ['/assets', '/favicon.ico'])
            ->set('http_parse_cookie', false);
    }
}
