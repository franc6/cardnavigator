<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Prompts and confirms the user's password before sensitive actions.
 */
class ConfirmablePasswordController
{
    /**
     * Show the confirm-password view.
     *
     * @return View The auth.confirm-password Blade view.
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * Validate the supplied password and, on success, mark the session as recently password-confirmed.
     *
     * @param  Request  $request  The HTTP request containing the password field.
     * @return RedirectResponse Redirect to the originally intended URL.
     *
     * @throws ValidationException When the supplied password is not valid for the authenticated user.
     */
    public function store(Request $request): RedirectResponse
    {
        if (
            ! Auth::guard('web')->validate([
                'email' => $request->user()->email,
                'password' => $request->password,
            ])
        ) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
