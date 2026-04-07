<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Displays the email verification prompt.
 */
class EmailVerificationPromptController
{
    /**
     * Display the email verification prompt, or redirect to the dashboard if already verified.
     *
     * @param  Request  $request  The HTTP request containing the authenticated user.
     * @return RedirectResponse|View Redirect to the dashboard when already verified, otherwise the auth.verify-email view.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(route('dashboard', absolute: false))
                    : view('auth.verify-email');
    }
}
