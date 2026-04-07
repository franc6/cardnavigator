<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Handles login/logout session lifecycle.
 */
class AuthenticatedSessionController
{
    /**
     * Display the login view.
     *
     * @return View The auth.login Blade view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Authenticate the user, regenerate the session, and redirect to the intended URL.
     *
     * @param  LoginRequest  $request  Validated login credentials (email, password, optional remember).
     * @return RedirectResponse Redirect to the originally intended URL, or the dashboard as a fallback.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Log the current user out, invalidate the session, and rotate the CSRF token.
     *
     * @param  Request  $request  The HTTP request containing the authenticated session.
     * @return RedirectResponse Redirect to the site root.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
