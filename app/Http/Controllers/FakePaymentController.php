<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Ordering\Enums\PaymentStatus;
use App\Domain\Ordering\Models\Payment;
use App\Domain\Payments\Actions\CompletePayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Fake gateway's 3-D Secure page: the guest approves or declines, and
 * returns to their order. Only ever sends the guest back to this site.
 */
final class FakePaymentController
{
    public function show(Request $request, string $reference): View|RedirectResponse
    {
        $payment = $this->payment($reference);

        if ($payment->status !== PaymentStatus::Pending) {
            return redirect()->to($this->returnUrl($request, $payment));
        }

        return view('payments.fake', [
            'payment' => $payment,
            'return' => $this->returnUrl($request, $payment),
        ]);
    }

    public function decide(Request $request, string $reference, CompletePayment $complete): RedirectResponse
    {
        $payment = $this->payment($reference);
        $approved = $request->input('decision') === 'approve';

        $complete->handle($payment, $approved, $approved ? null : 'declined_by_issuer');

        return redirect()->to($this->returnUrl($request, $payment));
    }

    private function payment(string $reference): Payment
    {
        return Payment::withoutTenancy()->with('order.restaurant')->where('reference', $reference)->firstOrFail();
    }

    private function returnUrl(Request $request, Payment $payment): string
    {
        $return = (string) $request->input('return', '');
        $fallback = route('customer.order', ['restaurant' => $payment->order->restaurant->slug, 'order' => $payment->order->short_code]);

        // Never redirect off-site: an open redirect would be a phishing tool.
        return str_starts_with($return, url('/').'/') ? $return : $fallback;
    }
}
