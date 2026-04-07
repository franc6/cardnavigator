<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    {{--
        Route URLs and translated labels are placed on data-* attributes so the
        <script> block below is pure JavaScript — no Blade expressions inside JS
        strings, which would confuse the IDE's language service.
    --}}
    <form id="login-form" method="POST"
          action="{{ route('login') }}"
          data-url-dashboard="{{ route('dashboard') }}"
          data-url-login-options="{{ route('webauthn.login.options') }}"
          data-url-login-verify="{{ route('webauthn.login') }}"
          data-url-register-options="{{ route('webauthn.register.options') }}"
          data-url-register-save="{{ route('webauthn.register') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" inputmode="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
            <p id="ajax-error-email" class="mt-2 text-sm text-red-600 hidden"></p>
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
            <p id="ajax-error-password" class="mt-2 text-sm text-red-600 hidden"></p>
        </div>

        <!-- Remember Me -->
        <div id="toggle-container" class="mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="toggle-switch" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
            </label>
        </div>

        <!-- Use Biometric (own row, right-aligned) -->
        <div class="mt-3">
            <label for="use_biometric" id="biometric-checkbox-label" class="inline-flex items-center" style="display:none">
                <input id="use_biometric" type="checkbox" class="toggle-switch">
                <span id="biometric-label-text" class="ms-2 text-sm text-gray-600"
                      data-label-ios="{{ __('Use Face ID / Touch ID') }}"
                      data-label-mac="{{ __('Use Touch ID') }}"
                      data-label-android="{{ __('Use Biometric') }}"
                      data-label-windows="{{ __('Use Windows Hello') }}"
                      data-label-generic="{{ __('Use Passkey') }}">{{ __('Use biometrics') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-end mt-4">
            @if (Route::has('password.request'))
                <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif

            <x-primary-button id="login-btn" class="ms-3">
                {{ __('Log in') }}
            </x-primary-button>
        </div>
    </form>

<style>
/* ---- base toggle ---- */
.toggle-switch {
    -webkit-appearance: none;
    appearance: none;
    position: relative;
    display: inline-block;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s, border-color 0.2s;
    vertical-align: middle;
}
.toggle-switch:focus { outline: none; }

/* ---- iOS / macOS style ---- */
.platform-ios .toggle-switch,
.platform-mac .toggle-switch {
    width: 51px;
    height: 31px;
    border-radius: 16px;
    background: #e5e7eb;
    border: none;
}
.platform-ios .toggle-switch:checked,
.platform-mac .toggle-switch:checked {
    background: #34c759;
}
.platform-ios .toggle-switch::before,
.platform-mac .toggle-switch::before {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 27px;
    height: 27px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 2px 5px rgba(0,0,0,0.3);
    transition: transform 0.2s;
}
.platform-ios .toggle-switch:checked::before,
.platform-mac .toggle-switch:checked::before {
    transform: translateX(20px);
}

/* ---- Android (Material 3) style ---- */
.platform-android .toggle-switch {
    width: 52px;
    height: 32px;
    border-radius: 16px;
    background: transparent;
    border: 2px solid #79747e;
}
.platform-android .toggle-switch:checked {
    background: #6750a4;
    border-color: #6750a4;
}
.platform-android .toggle-switch::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 4px;
    transform: translateY(-50%);
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #79747e;
    transition: transform 0.2s, width 0.1s, height 0.1s, left 0.1s;
}
.platform-android .toggle-switch:checked::before {
    left: 2px;
    width: 24px;
    height: 24px;
    transform: translateY(-50%) translateX(22px);
    background: #fff;
}

/* ---- Generic / desktop fallback ---- */
.platform-generic .toggle-switch {
    width: 44px;
    height: 24px;
    border-radius: 12px;
    background: #d1d5db;
    border: none;
}
.platform-generic .toggle-switch:checked {
    background: #6366f1;
}
.platform-generic .toggle-switch::before {
    content: '';
    position: absolute;
    top: 2px;
    left: 2px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    transition: transform 0.2s;
}
.platform-generic .toggle-switch:checked::before {
    transform: translateX(20px);
}
</style>

{{--
    type="module" ensures this script runs after app.js (also a module), which
    is what populates window.cn.webauthn. A classic <script> would execute
    during HTML parsing — before the deferred module script — and throw on the
    very first line (var wa = window.cn.webauthn).
--}}
<script type="module">
// ---------------------------------------------------------------------------
// DOM references — all Blade values come from data-* attributes above.
// ---------------------------------------------------------------------------

var wa = window.cn.webauthn;

var loginForm     = document.getElementById('login-form');
var emailInput    = document.getElementById('email');
var rememberMe    = document.getElementById('remember_me');
var biometricWrap = document.getElementById('biometric-checkbox-label');
var biometricBox  = document.getElementById('use_biometric');
var biometricText = document.getElementById('biometric-label-text');
var loginBtn      = document.getElementById('login-btn');
var errEmail      = document.getElementById('ajax-error-email');
var errPassword   = document.getElementById('ajax-error-password');

var urls = {
    login:           loginForm.action,
    dashboard:       loginForm.dataset.urlDashboard,
    loginOptions:    loginForm.dataset.urlLoginOptions,
    loginVerify:     loginForm.dataset.urlLoginVerify,
    registerOptions: loginForm.dataset.urlRegisterOptions,
    registerSave:    loginForm.dataset.urlRegisterSave,
};

// ---------------------------------------------------------------------------
// Platform detection — applied immediately so toggles render correctly
// ---------------------------------------------------------------------------

(function applyPlatformClass() {
    loginForm.classList.add('platform-' + wa.detectPlatform());
})();

// ---------------------------------------------------------------------------
// Synchronous restore — runs immediately so form is populated before paint
// ---------------------------------------------------------------------------

(function restoreState() {
    if (wa.getCookie('remember_pref') === '1') {
        rememberMe.checked = true;
        var saved = wa.getCookie('remember_email');
        if (saved && !emailInput.value) {
            emailInput.value = decodeURIComponent(saved);
        }
    }
})();

// ---------------------------------------------------------------------------
// Async init — show biometric checkbox only when the platform supports it
// ---------------------------------------------------------------------------

(async function initBiometric() {
    if (!window.PublicKeyCredential) { return; }
    var timeout = new Promise(function(resolve) { setTimeout(function() { resolve(false); }, 500); });
    var ok = await Promise.race([
        PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable().catch(function() { return false; }),
        timeout,
    ]);
    if (!ok) { return; }

    biometricText.textContent = wa.platformLabel(biometricText.dataset);
    biometricWrap.style.display = '';

    if (wa.getCookie('biometric_login') === '1') {
        biometricBox.checked = true;
        rememberMe.checked   = true;

        var saved = wa.getCookie('biometric_email');
        if (saved && !emailInput.value) {
            emailInput.value = decodeURIComponent(saved);
        }

        maybeAssert();
        setTimeout(maybeAssert, 600);
    }
})();

// ---------------------------------------------------------------------------
// Checkbox behaviour
// ---------------------------------------------------------------------------

rememberMe.addEventListener('change', function() {
    if (this.checked) {
        wa.setCookie('remember_pref', '1', 365);
    } else {
        wa.setCookie('remember_pref', '0', 0);
        wa.setCookie('remember_email', '', 0);
        // Unchecking remember me also clears biometric preference
        if (biometricBox.checked) {
            biometricBox.checked = false;
            wa.setCookie('biometric_login', '0', 0);
            wa.setCookie('biometric_email', '', 0);
        }
    }
});

biometricBox.addEventListener('change', function() {
    wa.setCookie('biometric_login', this.checked ? '1' : '0', this.checked ? 365 : 0);
    if (this.checked) {
        rememberMe.checked = true;
        wa.setCookie('remember_pref', '1', 365);
        maybeAssert();
    }
});

// ---------------------------------------------------------------------------
// Auto-assert when email is filled and biometric is checked
// ---------------------------------------------------------------------------

var assertTimer;
emailInput.addEventListener('input', function() {
    if (!biometricBox.checked) { return; }
    clearTimeout(assertTimer);
    assertTimer = setTimeout(maybeAssert, 400);
});

function maybeAssert() {
    if (biometricBox.checked && emailInput.value.trim()) {
        triggerAssertion(emailInput.value.trim());
    }
}

async function triggerAssertion(email) {
    try {
        var optRes = await fetch(urls.loginOptions, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body:    JSON.stringify({ email: email }),
        });
        if (!optRes.ok) { return; } // no passkey for this user — fall through to password

        var options = await optRes.json();

        // If the server found no credentials for this email, don't invoke the
        // authenticator — Firefox would show a confusing "create passkey" dialog
        // and Safari/iOS would silently reject with no visible feedback.
        if (!options.allowCredentials || options.allowCredentials.length === 0) { return; }

        wa.decodeAssertionOptions(options);

        var assertion = await navigator.credentials.get({ publicKey: options });

        var loginRes = await fetch(urls.loginVerify, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(wa.encodeAssertionPayload(assertion)),
        });

        if (loginRes.ok) {
            wa.setCookie('biometric_email', encodeURIComponent(email), 365);
            window.location.href = urls.dashboard;
        }

    } catch (e) {
        // NotAllowedError = dismissed / no credential — fall through to password form
    }
}

// ---------------------------------------------------------------------------
// Form submit — intercept when biometric is checked to register passkey after
// ---------------------------------------------------------------------------

// iOS Safari cannot call credentials.create() after two awaited fetches; the
// user-activation window expires.  On iOS we defer registration to a modal on
// the dashboard where a fresh button-tap fires credentials.create() directly.
// Firefox, Chrome (desktop & Android) preserve activation long enough for the
// inline path to work without any extra interaction.
var useModalForEnrollment = /iPad|iPhone|iPod/.test(navigator.userAgent);

loginForm.addEventListener('submit', async function(e) {
    // Persist the email for both paths whenever remember me is checked
    if (rememberMe.checked && emailInput.value.trim()) {
        wa.setCookie('remember_email', encodeURIComponent(emailInput.value.trim()), 365);
    }

    if (!biometricBox.checked) { return; } // normal submit — cookies saved above, let it proceed

    e.preventDefault();
    clearAjaxErrors();
    loginBtn.disabled = true;

    try {
        var res = await fetch(urls.login, {
            method:   'POST',
            redirect: 'follow',
            headers: {
                'Accept':       'application/json',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: new URLSearchParams(new FormData(loginForm)),
        });

        if (res.status === 422) {
            showAjaxErrors((await res.json()).errors || {});
            return;
        }

        if (res.status === 419) {
            window.location.reload();
            return;
        }

        wa.setCookie('biometric_email', encodeURIComponent(emailInput.value.trim()), 365);

        if (useModalForEnrollment) {
            wa.setCookieShort('want_passkey', 1, 300);
        } else { // Firefox / Chrome desktop & Android: inline registration works.
            await tryRegisterPasskey();
        }
        window.location.href = urls.dashboard;

    } catch (e) {
        window.location.href = urls.dashboard;
    } finally {
        loginBtn.disabled = false;
    }
});

async function tryRegisterPasskey() {
    try {
        var optRes = await fetch(urls.registerOptions, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        });
        if (!optRes.ok) { return; }

        var options = wa.decodeRegistrationOptions(await optRes.json());

        var credential = await navigator.credentials.create({ publicKey: options });

        await fetch(urls.registerSave, {
            method:  'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
            body: JSON.stringify(wa.encodeAttestationPayload(credential)),
        });
    } catch (e) {
        // NotAllowedError (cancelled), InvalidStateError (already exists) — continue
    }
}

// ---------------------------------------------------------------------------
// Inline error display for AJAX login failures
// ---------------------------------------------------------------------------

function clearAjaxErrors() {
    [errEmail, errPassword].forEach(function(el) { el.textContent = ''; el.classList.add('hidden'); });
}
function showAjaxErrors(errors) {
    if (errors.email)    { errEmail.textContent    = errors.email[0];    errEmail.classList.remove('hidden'); }
    if (errors.password) { errPassword.textContent = errors.password[0]; errPassword.classList.remove('hidden'); }
}
</script>
</x-guest-layout>
