<?php

use App\Domain\Integrations\Api\OpenApi;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\OrdersController;
use App\Http\Middleware\AuthenticateApiClient;
use Illuminate\Support\Facades\Route;

/*
| Public REST API v1 for POS and other systems. Every route needs an API key
| (Authorization: Bearer …) with the right scope; data is always the key's
| own restaurant. Described at /developers/api and /api/v1/openapi.json.
*/
Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('/openapi.json', fn () => response()->json(OpenApi::document()))->middleware('throttle:60,1')->name('openapi');

    Route::middleware([AuthenticateApiClient::class.':orders:read', 'throttle:api'])->group(function (): void {
        Route::get('/orders', [OrdersController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrdersController::class, 'show'])->whereNumber('order')->name('orders.show');
    });

    Route::post('/orders/{order}/status', [OrdersController::class, 'status'])
        ->whereNumber('order')
        ->middleware([AuthenticateApiClient::class.':orders:write', 'throttle:api'])
        ->name('orders.status');

    Route::middleware([AuthenticateApiClient::class.':menu:read', 'throttle:api'])->group(function (): void {
        Route::get('/branches', [MenuController::class, 'branches'])->name('branches.index');
        Route::get('/branches/{branch}/menu', [MenuController::class, 'menu'])->name('branches.menu');
    });

    Route::put('/branches/{branch}/products/{product}/availability', [MenuController::class, 'availability'])
        ->whereNumber('product')
        ->middleware([AuthenticateApiClient::class.':menu:write', 'throttle:api'])
        ->name('branches.availability');
});
