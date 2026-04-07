<?php

namespace App\Http\Controllers\WebAuthn;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laragear\WebAuthn\Http\Requests\AttestationRequest;
use Laragear\WebAuthn\Http\Requests\AttestedRequest;

use function response;

/**
 * Handles WebAuthn attestation (passkey registration and credential management).
 */
class WebAuthnRegisterController
{
    /**
     * Build the WebAuthn attestation challenge used by the browser's navigator.credentials.create() call.
     *
     * @param  AttestationRequest  $request  WebAuthn attestation request for the authenticated user.
     * @return Responsable A response object that serializes the attestation options as JSON.
     */
    public function options(AttestationRequest $request): Responsable
    {
        return $request->fastRegistration()->toCreate();
    }

    /**
     * Persist an attested WebAuthn credential as a new passkey on the authenticated user's account.
     *
     * @param  AttestedRequest  $request  WebAuthn attestation result POSTed by the browser.
     * @return Response 204 No Content on success.
     */
    public function register(AttestedRequest $request): Response
    {
        $request->save();

        return response()->noContent();
    }

    /**
     * Delete a registered passkey credential belonging to the authenticated user.
     *
     * @param  Request  $request  The HTTP request containing the authenticated user.
     * @param  string  $credentialId  Primary key of the WebAuthn credential to remove.
     * @return Response 204 No Content (also returned silently when no matching credential exists for this user).
     */
    public function destroy(Request $request, string $credentialId): Response
    {
        $request->user()
            ->webAuthnCredentials()
            ->whereKey($credentialId)
            ->delete();

        return response()->noContent();
    }
}
