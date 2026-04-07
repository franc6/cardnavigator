<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Response;
use Laragear\WebAuthn\Http\Requests\AssertedRequest;
use Laragear\WebAuthn\Http\Requests\AssertionRequest;

use function response;

/**
 * Handles WebAuthn assertion (passkey login).
 */
class WebAuthnLoginController
{
    /**
     * Build the WebAuthn assertion challenge used by the browser's navigator.credentials.get() call.
     *
     * @param  AssertionRequest  $request  WebAuthn assertion request optionally scoped by email.
     * @return Responsable A response object that serializes the assertion options as JSON.
     */
    public function options(AssertionRequest $request): Responsable
    {
        return $request->toVerify($request->validate(['email' => 'sometimes|email|string']));
    }

    /**
     * Verify the assertion and, on success, log the user in.
     *
     * @param  AssertedRequest  $request  WebAuthn assertion result POSTed by the browser.
     * @return Response 204 on a successful login, 422 when the assertion fails.
     */
    public function login(AssertedRequest $request): Response
    {
        return response()->noContent($request->login() ? 204 : 422);
    }
}
