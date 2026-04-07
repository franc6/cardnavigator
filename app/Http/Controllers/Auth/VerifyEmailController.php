<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Marks a user's email address as verified.
 */
class VerifyEmailController
{
    /**
     * Mark the authenticated user's email address as verified and fire the Verified event.
     *
     * @param  EmailVerificationRequest  $request  Signed verification request; route signature/throttle middleware enforce authenticity.
     * @return RedirectResponse Redirect to the dashboard with a "?verified=1" query string.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->markEmailAsVerified();
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard', absolute: false) . '?verified=1');
    }
}
