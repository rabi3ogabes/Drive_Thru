<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Branches\Links;
use App\Domain\Branches\Models\Branch;
use App\Domain\Branches\QrCodes;
use App\Domain\Tenancy\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Downloads a QR for the restaurant link or one branch link, as SVG, PNG or
 * a printable PDF poster. Any active team member of the restaurant may
 * download; the first download ticks the "print your QR" setup step.
 */
final class DownloadQrController
{
    public function __invoke(Request $request, Restaurant $restaurant, string $format, QrCodes $qr, Links $links): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->canAccessTenant($restaurant), 403);

        $branch = null;

        if ($request->filled('branch')) {
            $branch = Branch::withoutTenancy()
                ->where('restaurant_id', $restaurant->id)
                ->where('code', (string) $request->string('branch'))
                ->firstOrFail();
        }

        $stamps = $request->query('kind') === 'stamps';
        abort_if($stamps && $branch === null, 404);

        $url = $stamps && $branch !== null ? $links->stampCounter($restaurant, $branch) : $links->forQr($restaurant, $branch);
        $name = Str::slug($restaurant->slug.($branch !== null ? '-'.$branch->code : '').($stamps ? '-stamps' : '').'-qr');

        $this->markDownloaded($restaurant);

        return match ($format) {
            'svg' => response($qr->svg($url), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => "attachment; filename=\"{$name}.svg\"",
            ]),
            'png' => response($qr->png($url), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => "attachment; filename=\"{$name}.png\"",
            ]),
            default => response($qr->poster($restaurant, $branch, $url, $stamps ? 'stamps' : 'order'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$name}-poster.pdf\"",
            ]),
        };
    }

    private function markDownloaded(Restaurant $restaurant): void
    {
        $settings = $restaurant->settings ?? [];

        if (isset($settings['onboarding']['qr_downloaded_at'])) {
            return;
        }

        $settings['onboarding']['qr_downloaded_at'] = now()->toIso8601String();
        $restaurant->forceFill(['settings' => $settings])->saveQuietly();
    }
}
