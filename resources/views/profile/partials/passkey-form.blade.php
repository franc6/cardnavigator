<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Passkeys') }}</h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Use your device biometrics (Face ID, Touch ID, fingerprint, Windows Hello) to sign in without a password.') }}
        </p>
    </header>

    {{-- Existing credentials --}}
    @php $credentials = $user->webAuthnCredentials()->get(); @endphp

    @if ($credentials->isNotEmpty())
        <ul class="mt-4 divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
            @foreach ($credentials as $credential)
                <li class="flex items-center justify-between px-4 py-3 bg-white">
                    <div class="flex items-center gap-2">
                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 11c0-1.657 1.343-3 3-3s3 1.343 3 3v1H6v-1c0-1.657 1.343-3 3-3s3 1.343 3 3zm-6 4h12v5H6v-5z"/>
                        </svg>
                        <span class="text-sm text-gray-800">
                            {{ $credential->alias ?? __('Passkey registered :date', ['date' => $credential->created_at->toFormattedDayDateString()]) }}
                        </span>
                    </div>
                    <form method="POST"
                          data-credential-id="{{ $credential->id }}"
                          class="passkey-delete-form">
                        @csrf
                        @method('DELETE')
                        <x-danger-button type="button"
                                         onclick="deletePasskey(this)"
                                         class="text-xs py-1 px-2">
                            {{ __('Remove') }}
                        </x-danger-button>
                    </form>
                </li>
            @endforeach
        </ul>
    @else
        <p class="mt-4 text-sm text-gray-500">{{ __('No passkeys registered yet.') }}</p>
    @endif

    {{-- Add passkey button (shown only when browser supports WebAuthn) --}}
    <div class="mt-4" id="passkey-register-section" style="display:none">
        <x-primary-button type="button" id="passkey-register-btn">
            {{ __('Add Passkey') }}
        </x-primary-button>
        <p id="passkey-status" class="mt-2 text-sm text-gray-600 hidden"></p>
    </div>

    <p id="passkey-unsupported" class="mt-4 text-sm text-gray-500" style="display:none">
        {{ __('Your browser or device does not support passkeys.') }}
    </p>
</section>

<script>
(async function () {
    if (!window.PublicKeyCredential) {
        document.getElementById('passkey-unsupported').style.display = '';
        return;
    }

    const available = await PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(() => false);
    if (!available) {
        document.getElementById('passkey-unsupported').style.display = '';
        return;
    }

    document.getElementById('passkey-register-section').style.display = '';

    document.getElementById('passkey-register-btn').addEventListener('click', registerPasskey);
})();

async function registerPasskey() {
    const btn    = document.getElementById('passkey-register-btn');
    const status = document.getElementById('passkey-status');
    const wa     = window.cn.webauthn;

    btn.disabled = true;
    status.textContent = '';
    status.classList.add('hidden');

    try {
        // 1. Fetch options from server
        const optRes = await fetch('{{ route('webauthn.register.options') }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        });

        if (!optRes.ok) throw new Error(await optRes.text());

        // 2. Decode base64url fields the browser requires as ArrayBuffers
        const options = wa.decodeRegistrationOptions(await optRes.json());

        // 3. Invoke the authenticator
        const credential = await navigator.credentials.create({ publicKey: options });

        // 4. Save on server
        const saveRes = await fetch('{{ route('webauthn.register') }}', {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(wa.encodeAttestationPayload(credential)),
        });

        if (!saveRes.ok) throw new Error(await saveRes.text());

        status.textContent = '{{ __('Passkey registered! Reload to see it in the list.') }}';
        status.classList.remove('hidden');
        setTimeout(() => window.location.reload(), 1200);

    } catch (err) {
        status.textContent = err.message || '{{ __('Registration failed. Please try again.') }}';
        status.classList.remove('hidden');
    } finally {
        btn.disabled = false;
    }
}

async function deletePasskey(btn) {
    const form = btn.closest('form');
    const id   = form.dataset.credentialId;

    if (!confirm('{{ __('Remove this passkey?') }}')) return;

    try {
        const res = await fetch(`/webauthn/credentials/${encodeURIComponent(id)}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' },
        });

        if (!res.ok) throw new Error(await res.text());

        form.closest('li').remove();

    } catch (err) {
        alert(err.message || '{{ __('Could not remove passkey.') }}');
    }
}
</script>
