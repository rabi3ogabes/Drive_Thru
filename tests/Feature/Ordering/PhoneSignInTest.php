<?php

use App\Domain\Customers\Actions\SendOtp;
use App\Domain\Customers\Actions\VerifyOtp;
use App\Domain\Customers\Exceptions\OtpProblem;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\OtpRequest;
use App\Support\Phone;

it('normalizes the phone numbers guests actually type', function (string $input, ?string $expected) {
    expect(Phone::normalize($input))->toBe($expected);
})->with([
    ['5555 1234', '+97455551234'],
    ['٥٥٥٥١٢٣٤', '+97455551234'],
    ['+974 5555-1234', '+97455551234'],
    ['0097455551234', '+97455551234'],
    ['97455551234', '+97455551234'],
    ['+971 50 123 4567', '+971501234567'],
    ['123', null],
    ['', null],
]);

it('signs a guest in with a code and creates them per restaurant', function () {
    $restaurant = restaurant();
    $sent = app(SendOtp::class)->handle($restaurant, '5555 1234', '10.0.0.1');

    expect($sent['phone'])->toBe('+97455551234')
        ->and($sent['channel'])->toBe('whatsapp')
        ->and(OtpRequest::withoutTenancy()->sole()->code_hash)->not->toBe($sent['demo_code']);

    $customer = app(VerifyOtp::class)->handle($restaurant, '+97455551234', $sent['demo_code']);

    expect($customer->restaurant_id)->toBe($restaurant->id)
        ->and($customer->phone)->toBe('+97455551234');

    // The same phone at another restaurant is a different, separate customer.
    $other = restaurant(['en' => 'Other', 'ar' => 'آخر']);
    $this->travel(1)->minutes();
    $code = app(SendOtp::class)->handle($other, '55551234', '10.0.0.1')['demo_code'];
    $twin = app(VerifyOtp::class)->handle($other, '55551234', $code);

    expect($twin->id)->not->toBe($customer->id)
        ->and(Customer::withoutTenancy()->count())->toBe(2);
});

it('makes guests wait 30 seconds between codes and allows 5 per 10 minutes', function () {
    $restaurant = restaurant();
    app(SendOtp::class)->handle($restaurant, '55551234', '10.0.0.1');

    expect(fn () => app(SendOtp::class)->handle($restaurant, '55551234', '10.0.0.1'))->toThrow(OtpProblem::class);

    foreach (range(2, 5) as $_) {
        $this->travel(31)->seconds();
        app(SendOtp::class)->handle($restaurant, '55551234', '10.0.0.1');
    }

    $this->travel(31)->seconds();
    try {
        app(SendOtp::class)->handle($restaurant, '55551234', '10.0.0.1');
        $this->fail('The sixth code should be refused.');
    } catch (OtpProblem $e) {
        expect($e->reason)->toBe('limit');
    }
});

it('burns a code after 5 wrong tries and when it expires', function () {
    $restaurant = restaurant();
    $code = app(SendOtp::class)->handle($restaurant, '55551234', '10.0.0.1')['demo_code'];
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $_) {
        try {
            app(VerifyOtp::class)->handle($restaurant, '55551234', $wrong);
        } catch (OtpProblem) {
        }
    }

    expect(OtpRequest::withoutTenancy()->sole()->attempts)->toBe(5)
        ->and(fn () => app(VerifyOtp::class)->handle($restaurant, '55551234', $code))->toThrow(OtpProblem::class);

    $this->travel(1)->minutes();
    $fresh = app(SendOtp::class)->handle($restaurant, '55551234', '10.0.0.1')['demo_code'];
    $this->travel(6)->minutes();

    expect(fn () => app(VerifyOtp::class)->handle($restaurant, '55551234', $fresh))->toThrow(OtpProblem::class);
});
