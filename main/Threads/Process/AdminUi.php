<?php

declare(strict_types=1);

namespace Main\Threads\Process;

use Flytachi\Winter\Kernel\Kernel;
use Flytachi\Winter\Kernel\Process\Stereotype\Process;
use Swoole\Coroutine\Http\Client;
use Swoole\Coroutine\Http\Server;
use Swoole\Http\Request;
use Swoole\Http\Response;

/**
 * Панель управления на своём порту (`UI_PORT`, 9091): собранная SPA из
 * `frontend/.output/public` и `/api/*`, проброшенный на `/admin/*` бэкенда по loopback.
 *
 * Браузер говорит с одним origin — кука сессии работает как есть, CORS не нужен. Для
 * бэкенда запросы приходят с 127.0.0.1, поэтому настоящий адрес клиента едет в
 * `X-Forwarded-For`: блокировка входа считает по нему (loopback — доверенный прокси).
 */
final class AdminUi extends Process
{
    private const string ROOT = 'frontend/.output/public';

    /** Тело одного запроса: кусок загрузки до 16 МиБ и немного сверху, как у бэкенда. */
    private const int MAX_BODY = 32 * 1_048_576;

    private const array TYPES = [
        'html' => 'text/html; charset=utf-8',
        'js' => 'text/javascript; charset=utf-8',
        'css' => 'text/css; charset=utf-8',
        'json' => 'application/json',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
        'txt' => 'text/plain; charset=utf-8',
    ];

    /** Заголовки одного соединения: дальше прокси не едут ни в одну сторону. */
    private const array HOP = [
        'host', 'connection', 'keep-alive', 'transfer-encoding', 'te', 'upgrade',
        'proxy-connection', 'content-length', 'accept-encoding', 'content-encoding',
    ];

    protected float $grace = 5.0;

    private string $root = '';

    private int $backend = 9090;

    public function run(): void
    {
        $root = realpath(Kernel::$pathRoot . '/' . self::ROOT);
        if ($root === false || !is_file($root . '/200.html')) {
            $this->logger->warning(
                'admin panel is not built (no ' . self::ROOT . '): run `npm run generate` in frontend/',
            );
            return;
        }

        $this->root = $root;
        $this->backend = (int) env('SERVER_PORT', 9090);
        $port = (int) env('UI_PORT', 9091);

        $server = new Server('0.0.0.0', $port);
        $server->set([
            // Тело уходит бэкенду как пришло: multipart и куски загрузок не разбираются здесь.
            'http_parse_post' => false,
            'http_parse_files' => false,
            'http_parse_cookie' => false,
            'http_compression' => true,
            'package_max_length' => self::MAX_BODY,
        ]);
        $server->handle('/', function (Request $request, Response $response): void {
            $response->header('Server', 'Winter');
            $path = (string) ($request->server['request_uri'] ?? '/');
            if (str_starts_with($path, '/api/')) {
                $this->proxy($request, $response, $path);
            } else {
                $this->file($response, $path);
            }
        });

        $this->logger->info("Admin UI on http://0.0.0.0:{$port}");
        $server->start();
        $server->shutdown();
    }

    private function file(Response $response, string $path): void
    {
        $file = $this->resolve(rawurldecode($path));
        if ($file === null) {
            // Маршрут SPA: оболочка, а дальше разбирается роутер в браузере.
            $file = $this->root . '/200.html';
        }

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $response->header('Content-Type', self::TYPES[$ext] ?? 'application/octet-stream');
        // В `_nuxt/` имена с хешем содержимого — их можно держать в кэше вечно. Всё
        // остальное, оболочка прежде всего, проверяется на каждом заходе.
        $response->header('Cache-Control', str_starts_with($path, '/_nuxt/')
            ? 'public, max-age=31536000, immutable'
            : 'no-cache');
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->end((string) file_get_contents($file));
    }

    /** Файл сборки по пути запроса, или null. Наружу из каталога сборки не выходит. */
    private function resolve(string $path): ?string
    {
        foreach ([$path, rtrim($path, '/') . '/index.html'] as $candidate) {
            $real = realpath($this->root . $candidate);
            if ($real !== false && is_file($real) && str_starts_with($real, $this->root . '/')) {
                return $real;
            }
        }

        return null;
    }

    private function proxy(Request $request, Response $response, string $path): void
    {
        $headers = [];
        foreach ($request->header ?? [] as $name => $value) {
            if (!in_array($name, self::HOP, true)) {
                $headers[$name] = $value;
            }
        }
        $peer = (string) ($request->server['remote_addr'] ?? '');
        $headers['x-forwarded-for'] = isset($headers['x-forwarded-for'])
            ? $headers['x-forwarded-for'] . ', ' . $peer
            : $peer;
        // Addresses the backend writes into its answers (a file's URL, a temporary
        // link) must point at its own port on the host the browser used — not at
        // the loopback it is reached by here. PUBLIC_BASE_URL still has the last word.
        $host = (string) ($request->header['host'] ?? '127.0.0.1');
        $headers['x-forwarded-host'] = preg_replace('/:\d+$/', '', $host) . ':' . $this->backend;
        $headers['x-forwarded-proto'] ??= 'http';

        $query = (string) ($request->server['query_string'] ?? '');
        $client = new Client('127.0.0.1', $this->backend);
        $client->set(['timeout' => 120]);
        $client->setMethod((string) ($request->server['request_method'] ?? 'GET'));
        $client->setHeaders($headers);
        $body = $request->getContent();
        if ($body !== false && $body !== '') {
            $client->setData($body);
        }
        $client->execute('/admin' . substr($path, 4) . ($query === '' ? '' : '?' . $query));

        if ($client->statusCode < 0) {
            $this->logger->error('admin panel proxy: backend unreachable: ' . $client->errMsg);
            $response->status(502);
            $response->header('Content-Type', 'application/json');
            $response->end('{"code":502,"message":"Backend unreachable"}');
            $client->close();
            return;
        }

        $response->status($client->statusCode);
        foreach ($client->headers ?? [] as $name => $value) {
            if ($name !== 'set-cookie' && $name !== 'server' && !in_array($name, self::HOP, true)) {
                $response->header($name, $value);
            }
        }
        // Несколько кук — несколько строк заголовка: массив значений их и даёт.
        if (($client->set_cookie_headers ?? []) !== []) {
            $response->header('Set-Cookie', $client->set_cookie_headers);
        }
        $response->end((string) $client->body);
        $client->close();
    }
}
