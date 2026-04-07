<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Sends password reset links by email.
 */
class PasswordResetLinkController
{
    /**
     * Display the "forgot password" view that requests a reset link.
     *
     * @return View The auth.forgot-password Blade view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Validate the supplied email and send a password reset link via the configured broker.
     *
     * @param  Request  $request  The HTTP request containing the email field.
     * @return RedirectResponse Redirect back with a status when the link is sent, or with an email error otherwise.
     *
     * @throws ValidationException When the email field fails validation.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
