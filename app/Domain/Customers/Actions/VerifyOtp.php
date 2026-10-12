<?php

declare(strict_types=1);

namespace App\Domain\Customers\Actions;

use App\Domain\Customers\Exceptions\OtpProblem;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\OtpRequest;
use App\Domain\Tenancy\Models\Restaurant;
use App\Support\Digits;
use App\Support\Lang;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * Checks a sign-in code and returns the restaurant's customer for that
 * phone, creating them on first sign-in. Five wrong tries burn the code.
 */
final class VerifyOtp
{
    public const MAX_ATTEMPTS = 5;

    /**
     * @throws OtpProblem
     */
    public function handle(Restaurant $restaurant, string $phoneInput, string $code, ?string $locale = null): Customer
    {
        $phone = Phone::normalize($phoneInput) ?? throw new OtpProblem('phone', Lang::text('customers.otp.errors.phone'));
        $code = (string) preg_replace('/\D/', '', Digits::toLatin($code));

        // A wrong code must still count as an attempt, so problems are returned
        // out of the transaction (which commits) and thrown afterwards.
        $result = DB::transaction(function () use ($restaurant, $phone, $code, $locale): Customer|OtpProblem {
            $request = OtpRequest::withoutTenancy()
                ->where('restaurant_id', $restaurant->id)
                ->where('phone', $phone)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($request === null || ! $request->isUsable()) {
                return new OtpProblem('expired', Lang::text('customers.otp.errors.expired'));
            }

            if (! hash_equals($request->code_hash, SendOtp::hash($restaurant->id, $phone, $code))) {
                $request->increment('attempts');
                $left = self::MAX_ATTEMPTS - $request->attempts;

                return $left > 0
                    ? new OtpProblem('wrong', trans_choice('customers.otp.errors.wrong', $left, ['count' => $left]))
                    : new OtpProblem('expired', Lang::text('customers.otp.errors.too_many'));
            }

            $request->forceFill(['consumed_at' => now()])->save();

            $customer = Customer::withoutTenancy()->firstOrCreate(
                ['restaurant_id' => $restaurant->id, 'phone' => $phone],
                ['locale' => $locale ?? app()->getLocale()],
            );

            $customer->forceFill(['last_seen_at' => now()])->save();

            return $customer;
        });

        if ($result instanceof OtpProblem) {
            throw $result;
        }

        return $result;
    }
}
