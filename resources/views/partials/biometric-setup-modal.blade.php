{{--
    Biometric setup modal — shown on iOS after first login with biometric checkbox
    checked.  The button tap is a direct user gesture so credentials.create() fires
    with zero awaits before it, satisfying Safari's user-activation requirement.
    All Blade values are on data-* attributes; the <script> block is pure JS.
--}}
<div id="biometric-setup-overlay"
     style="display:none"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
    <div id="biometric-setup-modal"
         data-url-options="{{ route('webauthn.register.options') }}"
         data-url-save="{{ route('webauthn.register') }}"
         data-label-ios="{{ __('Finish setting up Face ID / Touch ID') }}"
         data-label-mac="{{ __('Finish setting up Touch ID') }}"
         data-label-android="{{ __('Finish setting up Biometric Sign-in') }}"
         data-label-windows="{{ __('Finish setting up Windows Hello') }}"
         data-label-generic="{{ __('Finish setup') }}"
         class="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-sm text-center">
        <div class="mx-auto mb-4 flex items-center justify-center h-16 w-16 rounded-full bg-indigo-50">
            <svg class="h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M7.864 4.243A7.5 7.5 0 0119.5 10.5c0 2.92-.556 5.709-1.568 8.268M5.742 6.364A7.465 7.465 0 004.5 10.5a7.464 7.464 0 01-1.15 3.993m1.989 3.559A11.209 11.209 0 008.25 10.5a3.75 3.75 0 117.5 0c0 .527-.021 1.049-.064 1.565M12 10.5a14.94 14.94 0 01-3.6 9.75m6.633-4.596a18.666 18.666 0 01-2.485 5.33" />
            </svg>
        </div>
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('One more step') }}
        </h2>
        <p class="mt-2 text-sm text-gray-600">
            {{ __('Tap the button below to finish enabling sign-in with your device biometrics.') }}
        </p>
        <x-primary-button type="button" id="biometric-setup-btn" class="mt-6 w-full justify-center">
            <span id="biometric-setup-btn-label">{{ __('Enable Biometric Sign-in') }}</span>
        </x-primary-button>
    </div>
</div>

{{--
    type="module" ensures this script runs after app.js (also a module), which
    is what populates window.cn.webauthn. A classic <script> would execute
    during HTML parsing — before the deferred module script — and throw on the
    very first line (var wa = window.cn.webauthn).
--}}
<script type="module">
(async function () {
    var wa = window.cn.webauthn;

    if (!wa.getCookie('want_passkey')) { return; }
    wa.setCookieShort('want_passkey', '', 0);

    if (!window.PublicKeyCredential) { return; }

    var overlay = document.getElementById('biometric-setup-overlay');
    var modal   = document.getElementById('biometric-setup-modal');
    var btn     = document.getElementById('biometric-setup-btn');
    var d       = modal.dataset;

    // Set platform-appropriate button label (no mention of "passkey").
    document.getElementById('biometric-setup-btn-label').textContent = wa.platformLabel(d);

    // Show the modal immediately — don't wait for the options fetch.
    // The button stays disabled until options are ready.
    btn.disabled = true;
    overlay.style.display = '';

    // Fetch and pre-decode registration options in the background.
    // Once ready, enable the button so the click fires credentials.create()
    // with ZERO awaits between the gesture and the call.
    var cachedOptions = null;
    try {
        var optRes = await fetch(d.urlOptions, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        });
        if (!optRes.ok) { overlay.style.display = 'none'; return; }

        cachedOptions = wa.decodeRegistrationOptions(await optRes.json());
    } catch (e) { overlay.style.display = 'none'; return; }

    btn.disabled = false;

    btn.addEventListener('click', function () {
        btn.disabled = true;

        // Direct user gesture → credentials.create() with ZERO awaits before this line.
        navigator.credentials.create({ publicKey: cachedOptions })
            .then(function (credential) {
                return fetch(d.urlSave, {
                    method:  'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify(wa.encodeAttestationPayload(credential)),
                });
            })
            .then(function () { overlay.style.display = 'none'; })
            .catch(function () { overlay.style.display = 'none'; });
    });
})();
</script>
