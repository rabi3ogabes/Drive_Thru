<?php

declare(strict_types=1);

namespace App\Support\Install;

use RuntimeException;

/** An install step that failed; the message is ready to show on the page. */
final class InstallProblem extends RuntimeException
{
    /**
     * @param  array<string, string>  $replace
     */
    public static function because(string $reason, array $replace = []): self
    {
        if (isset($replace['reason']) && __('install.reasons.'.$replace['reason']) !== 'install.reasons.'.$replace['reason']) {
            $replace['reason'] = __('install.reasons.'.$replace['reason']);
        }

        return new self(__('install.errors.'.$reason, $replace));
    }
}
