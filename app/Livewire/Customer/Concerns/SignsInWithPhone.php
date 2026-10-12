<?php

declare(strict_types=1);

namespace App\Livewire\Customer\Concerns;

use App\Domain\Customers\Actions\SendOtp;
use App\Domain\Customers\Actions\VerifyOtp;
use App\Domain\Customers\CustomerAuth;
use App\Domain\Customers\Exceptions\OtpProblem;
use App\Domain\Customers\Models\Customer;
use App\Support\Phone;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Phone + one-time code sign-in, inline wherever it is needed. Limits,
 * expiry and attempts are enforced by SendOtp / VerifyOtp on the server.
 *
 * @phpstan-require-extends Component
 */
trait SignsInWithPhone
{
    public string $phone = '';

    public string $code = '';

    #[Locked]
    public bool $codeSent = false;

    #[Locked]
    public int $resendIn = 0;

    #[Locked]
    public ?string $channel = null;

    /** Bumped on every send so the resend countdown restarts. */
    #[Locked]
    public int $sends = 0;

    /** Shown only in local/testing, where messages are not really sent. */
    #[Locked]
    public ?string $demoCode = null;

    public ?string $otpError = null;

    public function sendCode(): void
    {
        $this->otpError = null;

        try {
            $sent = app(SendOtp::class)->handle($this->restaurant, $this->phone, request()->ip());
        } catch (OtpProblem $problem) {
            $this->otpError = $problem->getMessage();
            $this->resendIn = $problem->retryInSeconds ?? $this->resendIn;
            $this->codeSent = $this->codeSent || $problem->reason === 'resend';
            $this->sends++;

            return;
        }

        $this->phone = Phone::format($sent['phone']);
        $this->codeSent = true;
        $this->code = '';
        $this->channel = $sent['channel'];
        $this->resendIn = $sent['resend_in'];
        $this->demoCode = $sent['demo_code'] ?? null;
        $this->sends++;
    }

    public function verifyCode(): void
    {
        $this->otpError = null;

        try {
            $customer = app(VerifyOtp::class)->handle($this->restaurant, $this->phone, $this->code, app()->getLocale());
        } catch (OtpProblem $problem) {
            $this->otpError = $problem->getMessage();
            $this->code = '';

            return;
        }

        app(CustomerAuth::class)->login($this->restaurant, $customer);
        $this->reset('code', 'codeSent', 'resendIn', 'demoCode', 'channel');
        $this->signedIn($customer);
    }

    public function changePhone(): void
    {
        $this->reset('code', 'codeSent', 'resendIn', 'demoCode', 'channel', 'otpError');
        $this->phone = (string) preg_replace('/^\+974\s?/', '', $this->phone);
    }

    abstract protected function signedIn(Customer $customer): void;
}
