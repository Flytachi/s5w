<?php

declare(strict_types=1);

namespace Main\Dto;

final class AccessTokenSecretRes
{
    public function __construct(
        public string $token,
        public AccessTokenRes $accessToken,
    ) {
    }
}
