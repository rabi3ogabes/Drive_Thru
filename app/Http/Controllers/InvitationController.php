<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Access\Actions\AcceptInvitation;
use App\Domain\Tenancy\Models\Restaurant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * The page behind every invitation link: choose a password, land signed in
 * inside the right workspace.
 */
final class InvitationController
{
    public function __construct(private readonly AcceptInvitation $accept) {}

    public function show(string $token): Response
    {
        $invitation = $this->accept->find($token);

        if ($invitation === null) {
            return response()->view('invitation.expired', status: 410);
        }

        $user = $invitation->user;

        return response()->view('invitation.accept', [
            'token' => $token,
            'user' => $user,
            'restaurants' => $user->restaurants()->get(),
            'isPlatform' => $user->isPlatformStaff(),
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse|Response
    {
        $invitation = $this->accept->find($token);

        if ($invitation === null) {
            return response()->view('invitation.expired', status: 410);
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ], attributes: ['password' => __('access.invitation.password')]);

        $user = $this->accept->handle($invitation, $validated['password']);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        if ($user->isPlatformStaff()) {
            return redirect()->to('/admin');
        }

        $restaurant = $user->accessibleRestaurants()->first();

        return redirect()->to($restaurant instanceof Restaurant ? '/app/'.$restaurant->slug : '/app');
    }
}
