<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * Manages the authenticated user's profile.
 */
class ProfileController
{
    /**
     * Display the user's profile form.
     *
     * @param  Request  $request  The HTTP request containing the authenticated user.
     * @return View The profile.edit Blade view, bound to the current user.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information, clearing email verification when the email changes.
     *
     * @param  ProfileUpdateRequest  $request  Validated profile update fields (name, email).
     * @return RedirectResponse Redirect back to the profile.edit route with a profile-updated status.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the authenticated user's account after confirming their current password.
     *
     * @param  Request  $request  The HTTP request containing the user's current password in the userDeletion bag.
     * @return RedirectResponse Redirect to the site root after logging the user out and destroying their session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
