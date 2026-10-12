<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Restaurant;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridges Filament's resolved tenant into TenantContext so every tenant model
 * query in the restaurant panel is scoped automatically.
 */
final class ApplyTenantContext
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Restaurant) {
            $this->context->set($tenant);
        }

        return $next($request);
    }
}
