/**
 * WebAuthn / passkey helpers shared across login.blade.php, passkey-form.blade.php,
 * and biometric-setup-modal.blade.php. Each helper is a small, side-effect-light
 * function with no Blade dependencies so it can be unit-tested under Jest's jsdom
 * environment.
 *
 * The default export, `webauthn`, bundles the public surface together for
 * window.cn.webauthn assignment in app.js.
 */

/**
 * Decode a base64url-encoded string into an ArrayBuffer the WebAuthn APIs accept.
 *
 * @param {string} str  base64url-encoded payload.
 * @returns {ArrayBuffer}
 */
export function base64urlDecode(str) {
    let s = str.replace(/-/g, '+').replace(/_/g, '/');
    while (s.length % 4) { s += '='; }
    return Uint8Array.from(atob(s), c => c.charCodeAt(0)).buffer;
}

/**
 * Encode an ArrayBuffer or Uint8Array as a base64url string for transport to the server.
 *
 * @param {ArrayBuffer|ArrayBufferView} buf  Binary payload.
 * @returns {string}
 */
export function base64urlEncode(buf) {
    return btoa(String.fromCharCode.apply(null, new Uint8Array(buf)))
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=/g, '');
}

/**
 * Read a single document.cookie value by name.
 *
 * @param {string} name  Cookie name.
 * @returns {string|null} The cookie's raw value, or null if missing.
 */
export function getCookie(name) {
    const match = document.cookie.split('; ').find(r => r.startsWith(name + '='));
    return match ? match.split('=').slice(1).join('=') : null;
}

/**
 * Set a cookie with an explicit max-age in seconds. A non-positive lifetime expires the cookie immediately.
 *
 * @param {string} name
 * @param {string|number} value
 * @param {number} seconds
 */
export function setCookieShort(name, value, seconds) {
    const age = seconds > 0 ? 'max-age=' + seconds : 'max-age=0';
    document.cookie = name + '=' + value + '; ' + age + '; path=/; SameSite=Lax';
}

/**
 * Set a cookie with a lifetime in days.
 *
 * @param {string} name
 * @param {string|number} value
 * @param {number} days
 */
export function setCookie(name, value, days) {
    setCookieShort(name, value, days * 86400);
}

/**
 * Detect the user-agent platform for selecting a biometric label / toggle style.
 *
 * @param {string} [userAgent]  Defaults to navigator.userAgent. Accept an arg so Jest can pass synthetic UAs.
 * @returns {'ios'|'mac'|'android'|'windows'|'generic'}
 */
export function detectPlatform(userAgent = navigator.userAgent) {
    if (/iPad|iPhone|iPod/.test(userAgent)) { return 'ios'; }
    if (/Macintosh/.test(userAgent))        { return 'mac'; }
    if (/Android/.test(userAgent))          { return 'android'; }
    if (/Win/.test(userAgent))              { return 'windows'; }
    return 'generic';
}

/**
 * Pick the right biometric label for the current platform from a labels-by-platform dataset
 * (the data-label-* attributes on the inline checkbox span).
 *
 * @param {{labelIos: string, labelMac: string, labelAndroid: string, labelWindows: string, labelGeneric: string}} labels
 * @param {string} [userAgent]
 * @returns {string}
 */
export function platformLabel(labels, userAgent = navigator.userAgent) {
    switch (detectPlatform(userAgent)) {
        case 'ios':     return labels.labelIos;
        case 'mac':     return labels.labelMac;
        case 'android': return labels.labelAndroid;
        case 'windows': return labels.labelWindows;
        default:        return labels.labelGeneric;
    }
}

/**
 * Decode the base64url fields inside an attestation/registration options payload in place,
 * converting challenge, user.id, and any excludeCredentials[].id values to ArrayBuffer.
 *
 * @param {object} options  Server-supplied registration options. Mutated and returned.
 * @returns {object}
 */
export function decodeRegistrationOptions(options) {
    options.challenge = base64urlDecode(options.challenge);
    options.user.id   = base64urlDecode(options.user.id);
    if (options.excludeCredentials) {
        options.excludeCredentials = options.excludeCredentials.map(c => ({
            ...c,
            id: base64urlDecode(c.id),
        }));
    }
    return options;
}

/**
 * Decode the base64url fields inside an assertion/login options payload in place,
 * converting challenge and any allowCredentials[].id values to ArrayBuffer.
 *
 * @param {object} options  Server-supplied assertion options. Mutated and returned.
 * @returns {object}
 */
export function decodeAssertionOptions(options) {
    options.challenge = base64urlDecode(options.challenge);
    if (options.allowCredentials) {
        options.allowCredentials = options.allowCredentials.map(c => ({
            ...c,
            id: base64urlDecode(c.id),
        }));
    }
    return options;
}

/**
 * Build the JSON payload posted back to the server after navigator.credentials.create() resolves.
 *
 * @param {PublicKeyCredential} credential
 * @returns {object} A plain object suitable for JSON.stringify.
 */
export function encodeAttestationPayload(credential) {
    return {
        id:       credential.id,
        rawId:    base64urlEncode(credential.rawId),
        type:     credential.type,
        response: {
            clientDataJSON:    base64urlEncode(credential.response.clientDataJSON),
            attestationObject: base64urlEncode(credential.response.attestationObject),
        },
    };
}

/**
 * Build the JSON payload posted back to the server after navigator.credentials.get() resolves.
 *
 * @param {PublicKeyCredential} assertion
 * @returns {object} A plain object suitable for JSON.stringify.
 */
export function encodeAssertionPayload(assertion) {
    return {
        id:       assertion.id,
        rawId:    base64urlEncode(assertion.rawId),
        type:     assertion.type,
        response: {
            clientDataJSON:    base64urlEncode(assertion.response.clientDataJSON),
            authenticatorData: base64urlEncode(assertion.response.authenticatorData),
            signature:         base64urlEncode(assertion.response.signature),
            userHandle:        assertion.response.userHandle
                                   ? base64urlEncode(assertion.response.userHandle)
                                   : null,
        },
    };
}

const webauthn = {
    base64urlDecode,
    base64urlEncode,
    getCookie,
    setCookie,
    setCookieShort,
    detectPlatform,
    platformLabel,
    decodeRegistrationOptions,
    decodeAssertionOptions,
    encodeAttestationPayload,
    encodeAssertionPayload,
};

export default webauthn;
