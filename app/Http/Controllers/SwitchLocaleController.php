<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Locales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Uri;

final class SwitchLocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(Locales::isSupported($locale), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->saveQuietly();

        Cookie::queue(Cookie::forever(config('locales.cookie'), $locale));

        // Return to the same page, minus any ?lang= that would override the new choice.
        $back = url()->previous();
        $target = str_starts_with($back, url('/')) ? (string) Uri::of($back)->withoutQuery(['lang']) : url('/');

        return redirect()->to($target);
    }
}
