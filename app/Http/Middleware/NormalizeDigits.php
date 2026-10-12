<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Digits;
use Illuminate\Foundation\Http\Middleware\TransformsRequest;

/**
 * Converts Arabic-Indic digits in form input to Western digits. Livewire
 * requests are skipped (their signed snapshots must stay byte-identical);
 * Filament inputs normalize themselves on dehydration instead.
 */
final class NormalizeDigits extends TransformsRequest
{
    /** @var list<string> */
    private array $except = ['password', 'password_confirmation', 'current_password'];

    public function handle($request, \Closure $next)
    {
        if ($request->headers->has('X-Livewire')) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    protected function transform($key, $value)
    {
        if (in_array($key, $this->except, true) || ! is_string($value)) {
            return $value;
        }

        return Digits::toLatin($value);
    }
}
