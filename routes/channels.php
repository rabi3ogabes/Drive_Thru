<?php

use App\Domain\Ordering\Realtime\ChannelAccess;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
| Staff signed in to the panel (web guard). Paired screens and guests are
| authorised by App\Http\Controllers\RealtimeAuthController, because they
| are not users.
*/

Broadcast::channel('App.Models.User.{id}', fn (User $user, int $id): bool => $user->id === $id);

Broadcast::channel('branch.{branchId}.orders', fn (User $user, int $branchId): bool => app(ChannelAccess::class)->branchOrders($user, $branchId));

Broadcast::channel('branch.{branchId}.pager', fn (User $user, int $branchId): bool => app(ChannelAccess::class)->branchPager($user, $branchId));

Broadcast::channel('restaurant.{restaurantId}.dashboard', fn (User $user, int $restaurantId): bool => app(ChannelAccess::class)->restaurantDashboard($user, $restaurantId));

Broadcast::channel('platform.dashboard', fn (User $user): bool => app(ChannelAccess::class)->platformDashboard($user));
