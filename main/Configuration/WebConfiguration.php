<?php

namespace Main\Configuration;

use Flytachi\Winter\Kernel\App\ApplicationArguments;
use Flytachi\Winter\Kernel\App\Config\ServerSettings;
use Flytachi\Winter\Kernel\App\Config\WebConfigurerAdapter;

class WebConfiguration extends WebConfigurerAdapter
{
    public function configureServer(ServerSettings $server, ApplicationArguments $args): void
    {
        $server->maxRequestSize(300 * 1024 * 1024);

        $server->staticPath('resources/static')
            ->set('static_handler_locations', ['/assets', '/favicon.ico'])
            ->set('http_parse_cookie', false);
    }
}