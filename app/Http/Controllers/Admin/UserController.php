<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Admin controller for managing application user accounts.
 */
class UserController
{
    /**
     * Display the list of all users.
     *
     * @return View The admin user list Blade view.
     */
    public function index(): View
    {
        return view('admin.users', [
            'users' => User::orderBy('name', 'ASC')->get(),
        ]);
    }

    /**
     * Create a new user account.
     *
     * @param  Request  $request  The HTTP request containing name, email, password, password_confirmation, and optional force_password_change.
     * @return RedirectResponse Redirect to the admin users list with a success status.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'force_password_change' => 'boolean',
        ]);

        User::create($validated);

        return redirect()->route('admin.users.index')->with('status', __('User created.'));
    }

    /**
     * Reset a user's password.
     *
     * @param  Request  $request  The HTTP request containing the new password, confirmation, and optional force_password_change flag.
     * @param  User  $user  The user whose password is being reset.
     * @return RedirectResponse Redirect to the admin users list with a confirmation message.
     */
    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|min:8|confirmed',
            'force_password_change' => 'boolean',
        ]);

        $user->update([
            'password' => $validated['password'],
            'force_password_change' => $request->boolean('force_password_change'),
        ]);

        return redirect()->route('admin.users.index')->with('status', __('Password updated for :name.', ['name' => $user->name]));
    }

    /**
     * Delete a user account. Self-deletion is forbidden.
     *
     * @param  User  $user  The user account to delete; must not be the currently authenticated user.
     * @return RedirectResponse Redirect to the admin users list with a success status.
     */
    public function destroy(User $user): RedirectResponse
    {
        abort_if($user->is(Auth::user()), 403, __('You cannot delete your own account here.'));

        $user->delete();

        return redirect()->route('admin.users.index')->with('status', __('User deleted.'));
    }
}
