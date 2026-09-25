<?php

declare(strict_types=1);

namespace Arya\Middleware;

final class GuestMiddleware
{
    public function handle(): void
    {
        if (is_auth()) {
            redirect('dashboard');
        }
    }
}
