<?php

use Illuminate\Support\Facades\Schedule;

// Billing runs early in the morning, Doha time, before restaurants open.
Schedule::command('billing:run')
    ->dailyAt('05:30')
    ->timezone('Asia/Qatar')
    ->withoutOverlapping()
    ->onOneServer();

// Held orders go to the kitchen at their start time (a queued job does it on time; this is the safety net).
Schedule::command('orders:release')->everyMinute()->withoutOverlapping()->onOneServer();

// Late orders and guests waiting at the window: the branch team is alerted once per order.
Schedule::command('ops:alerts')->everyMinute()->withoutOverlapping()->onOneServer();

// Webhook retries with backoff (first attempts go straight to the queue).
Schedule::command('webhooks:retry')->everyMinute()->withoutOverlapping()->onOneServer();

// Outside providers failing most calls: alert the platform admins (at most hourly per provider).
Schedule::command('providers:watch')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Unpaid online orders: checked with the provider, then paid or cancelled.
Schedule::command('payments:expire')->everyFiveMinutes()->withoutOverlapping()->onOneServer();

// Loyalty: expire old points and wallet rewards, move guests between tiers (quiet hours, Doha time).
Schedule::command('loyalty:maintain')->dailyAt('03:30')->timezone('Asia/Qatar')->withoutOverlapping()->onOneServer();

Schedule::command('pager:expire')->everyFifteenMinutes()->withoutOverlapping()->onOneServer();

Schedule::command('model:prune')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
