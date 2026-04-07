<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Resends email verification notifications.
 */
class EmailVerificationNotificationController
{
    /**
     * Send a new email verification notification, short-circuiting to the dashboard if the user
     * has already verified their email.
     *
     * @param  Request  $request  The HTTP request containing the authenticated user.
     * @return RedirectResponse Redirect to the dashboard when already verified, otherwise back with a verification-link-sent status.
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
