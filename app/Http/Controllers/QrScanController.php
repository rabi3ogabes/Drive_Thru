<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Campaigns\Models\QrCampaign;
use App\Domain\Campaigns\QrTracking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** /q/{code}: a tracked QR code. Counts the scan, then opens the menu it was printed for. */
final class QrScanController
{
    public function __invoke(Request $request, string $code, QrTracking $tracking): RedirectResponse
    {
        $campaign = QrCampaign::withoutTenancy()->with(['restaurant', 'branch'])->where('code', strtoupper($code))->first();

        abort_if($campaign === null, 404);

        // Switched off or out of its dates: the restaurant page, uncounted.
        if (! $campaign->isLive()) {
            return redirect()->route('public.restaurant', $campaign->restaurant);
        }

        $tracking->scan($campaign, $request);

        return redirect()->to($tracking->destination($campaign));
    }
}
