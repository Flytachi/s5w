<?php

declare(strict_types=1);

namespace Main\Controllers\Requests;

use Flytachi\Winter\Kernel\Http\Request\Validation\Required;
use Main\Enum\TokenStatus;

final class TokenStatusRequest
{
    public function __construct(
        #[Required]
        public TokenStatus $status,
    ) {
    }
}
