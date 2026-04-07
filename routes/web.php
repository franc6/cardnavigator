<?php

use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\ForcedPasswordChangeController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\NearbyBusinessController;
use App\Http\Controllers\PercentageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WebAuthn\WebAuthnLoginController;
use App\Http\Controllers\WebAuthn\WebAuthnRegisterController;
use App\Http\Middleware\RequireAdmin;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

Route::get('/favicon.ico', function () {
    return response()->file(public_path('images/favicon.ico'));
})->name('favicon');

Route::get('/manifest.json', function () {
    return response()->view('manifest')->header('Content-Type', 'application/manifest+json');
})->name('manifest');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [NearbyBusinessController::class, 'index'])
        ->name('dashboard');

    Route::post('/dashboard/search', [NearbyBusinessController::class, 'search'])
        ->name('dashboard.search');

    Route::post('/places/{placeId}/category', [CategoryController::class, 'update'])
        ->name('places.category.update');

    Route::get('/places/{placeId}', [NearbyBusinessController::class, 'show'])
        ->name('places.show');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('cards', CardController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('/cards/{card}/image', [CardController::class, 'image'])->name('cards.image');
    Route::get('/percentages', [PercentageController::class, 'index'])->name('percentages.index');
    Route::post('/percentages', [PercentageController::class, 'update'])->name('percentages.update');

    Route::get('/password/change', [ForcedPasswordChangeController::class, 'show'])->name('password.change');
    Route::put('/password/change', [ForcedPasswordChangeController::class, 'update'])->name('password.change.update');

    Route::prefix('admin')->name('admin.')->middleware(RequireAdmin::class)->group(function () {
        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');

        Route::get('/database', [DatabaseController::class, 'index'])->name('database.index');
        Route::post('/database/migrate', [DatabaseController::class, 'migrate'])->name('database.migrate');
        Route::post('/database/seed', [DatabaseController::class, 'seed'])->name('database.seed');
    });
});

// WebAuthn — passkey registration (authenticated users only)
Route::middleware(['auth', 'verified'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->group(function () {
        Route::post('/webauthn/register/options', [WebAuthnRegisterController::class, 'options'])
            ->name('webauthn.register.options');
        Route::post('/webauthn/register', [WebAuthnRegisterController::class, 'register'])
            ->name('webauthn.register');
        Route::delete('/webauthn/credentials/{credentialId}', [WebAuthnRegisterController::class, 'destroy'])
            ->name('webauthn.destroy');
    });

// WebAuthn — passkey assertion (guest only)
Route::middleware('guest')
    ->withoutMiddleware(PreventRequestForgery::class)
    ->group(function () {
        Route::post('/webauthn/login/options', [WebAuthnLoginController::class, 'options'])
            ->name('webauthn.login.options');
        Route::post('/webauthn/login', [WebAuthnLoginController::class, 'login'])
            ->name('webauthn.login');
    });

require __DIR__ . '/auth.php';
