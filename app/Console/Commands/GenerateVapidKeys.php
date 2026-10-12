<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

/** Creates the VAPID key pair for web push and writes it to .env (once). */
final class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:keys {--force : Replace keys that already exist (existing subscriptions stop working)}';

    protected $description = 'Create the VAPID keys used to send web push notifications';

    public function handle(): int
    {
        $path = base_path('.env');
        $env = is_file($path) ? (string) file_get_contents($path) : '';

        if (preg_match('/^VAPID_PRIVATE_KEY=\S+/m', $env) === 1 && ! $this->option('force')) {
            $this->components->info('VAPID keys already exist. Use --force to replace them.');

            return self::SUCCESS;
        }

        $keys = VAPID::createVapidKeys();

        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $env = preg_match("/^{$name}=.*$/m", $env) === 1
                ? (string) preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $env)
                : rtrim($env, "\n")."\n{$name}={$value}\n";
        }

        file_put_contents($path, $env);
        $this->components->info('VAPID keys written to .env.');

        return self::SUCCESS;
    }
}
