<?php

declare(strict_types=1);

namespace Main\Service;

use Flytachi\Winter\Base\HttpCode;
use Flytachi\Winter\DI\Attribute\Autowired;
use Flytachi\Winter\DI\Attribute\Singleton;
use Flytachi\Winter\Kernel\Exception\ClientError;
use Flytachi\Winter\Kernel\Http\Response\ResponseException;
use Main\Request\LoginRequest;
use Main\Support\AdminSession;
use Psr\Log\LoggerInterface;

#[Singleton]
final class AdminAuthService
{
    private const int MAX_ATTEMPTS = 5;
    private const int WINDOW = 900;
    private const int LOCK = 300;

    private array $attempts = [];

    #[Autowired]
    private LoggerInterface $log;

    public function isConfigured(): bool
    {
        return (string) env('ADMIN_LOGIN', '') !== '' && (string) env('ADMIN_PASSWORD', '') !== '';
    }

    /**
     * @return array{token: string, expiresAt: int}
     */
    public function login(LoginRequest $request, string $ip): array
    {
        $this->assertNotLocked($ip);

        $login = (string) env('ADMIN_LOGIN', '');
        $password = (string) env('ADMIN_PASSWORD', '');

        if (!$this->isConfigured()) {
            $this->log->error('ADMIN_LOGIN or ADMIN_PASSWORD is missing: admin is locked');
            ClientError::throw('Админка заперта: не заданы ADMIN_LOGIN и ADMIN_PASSWORD', HttpCode::SERVICE_UNAVAILABLE);
        }

        $loginOk = hash_equals($login, $request->login);
        $passwordOk = hash_equals($password, $request->password);

        if (!$loginOk || !$passwordOk) {
            $this->remember($ip);
            $this->log->warning("Failed admin login from {$ip}");
            ClientError::throw('Неверный логин или пароль', HttpCode::UNAUTHORIZED);
        }

        unset($this->attempts[$ip]);
        $this->log->info("Admin logged in from {$ip}");

        return AdminSession::issue($this->fingerprint($login, $password));
    }

    public function verify(?string $token): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        return AdminSession::verify(
            $token,
            $this->fingerprint((string) env('ADMIN_LOGIN', ''), (string) env('ADMIN_PASSWORD', '')),
        );
    }

    private function fingerprint(string $login, string $password): string
    {
        return hash('sha256', $login . "\0" . $password);
    }

    private function assertNotLocked(string $ip): void
    {
        $state = $this->attempts[$ip] ?? null;
        if ($state !== null && $state['until'] > time()) {
            throw (new ResponseException('Слишком много попыток, подождите', HttpCode::TOO_MANY_REQUESTS))
                ->withHeader('Retry-After', (string) ($state['until'] - time()));
        }
    }

    private function remember(string $ip): void
    {
        $now = time();
        $state = $this->attempts[$ip] ?? ['fails' => 0, 'since' => $now, 'until' => 0];

        if ($now - $state['since'] > self::WINDOW) {
            $state = ['fails' => 0, 'since' => $now, 'until' => 0];
        }

        $state['fails']++;
        if ($state['fails'] >= self::MAX_ATTEMPTS) {
            $state['until'] = $now + self::LOCK;
            $state['fails'] = 0;
            $state['since'] = $now;
        }

        $this->attempts[$ip] = $state;
    }
}
