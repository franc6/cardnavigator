<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Handles the forced password change flow for accounts that require a new password on next login.
 */
class ForcedPasswordChangeController
{
    /**
     * Display the forced password change form.
     *
     * @return View The forced-password-change Blade view.
     */
    public function show(): View
    {
        return view('auth.forced-password-change');
    }

    /**
     * Validate and save the new password, clearing the force-change flag.
     *
     * @param  Request  $request  The HTTP request containing the new password and its confirmation.
     * @return RedirectResponse Redirect to the dashboard after the password is updated.
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update([
            'password' => $request->password,
            'force_password_change' => false,
        ]);

        return redirect()->route('dashboard')->with('status', __('Password updated successfully.'));
    }
}
