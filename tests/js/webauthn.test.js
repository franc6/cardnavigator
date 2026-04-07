/**
 * Unit tests for the shared WebAuthn helpers in resources/js/webauthn.js.
 *
 * Runs under Jest's jsdom environment so the cookie helpers can read and write
 * document.cookie. navigator.credentials is intentionally NOT exercised here —
 * that requires a virtual authenticator and belongs in a browser-level
 * Playwright suite.
 */

import {
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
} from '../../resources/js/webauthn.js';

/**
 * Convert an ArrayBuffer to a plain JS array of byte values so equality is
 * easy to assert. ArrayBuffers don't implement structural equality directly.
 */
function bytes(buf) {
    return Array.from(new Uint8Array(buf));
}

/**
 * Clear every cookie visible to jsdom so cookie tests start in a clean state.
 */
function clearCookies() {
    document.cookie.split('; ').forEach(c => {
        const name = c.split('=')[0];
        if (name) {
            document.cookie = name + '=; max-age=0; path=/';
        }
    });
}

describe('base64url round-trip', () => {
    test('encodes and decodes ASCII input losslessly', () => {
        // Arrange
        const text = 'hello world';
        const buf  = new TextEncoder().encode(text).buffer;

        // Act
        const encoded = base64urlEncode(buf);
        const decoded = base64urlDecode(encoded);

        // Assert
        expect(new TextDecoder().decode(decoded)).toBe(text);
        expect(encoded).not.toContain('=');
        expect(encoded).not.toContain('+');
        expect(encoded).not.toContain('/');
    });

    test('encodes and decodes binary bytes losslessly', () => {
        // Arrange
        const original = new Uint8Array([0, 1, 2, 250, 251, 252, 253, 254, 255]);

        // Act
        const encoded = base64urlEncode(original.buffer);
        const decoded = base64urlDecode(encoded);

        // Assert
        expect(bytes(decoded)).toEqual(Array.from(original));
    });

    test('decodes a base64url string with no padding', () => {
        // Arrange — "foo" base64-encodes to "Zm9v" (no padding); base64url keeps it identical.
        const encoded = 'Zm9v';

        // Act
        const decoded = base64urlDecode(encoded);

        // Assert
        expect(new TextDecoder().decode(decoded)).toBe('foo');
    });
});

describe('cookie helpers', () => {
    beforeEach(clearCookies);

    test('getCookie returns null when the cookie is missing', () => {
        // Arrange — no cookies set.
        // Act
        const value = getCookie('missing');

        // Assert
        expect(value).toBeNull();
    });

    test('setCookie writes a cookie that getCookie can read back', () => {
        // Arrange
        setCookie('remember_pref', '1', 365);

        // Act
        const value = getCookie('remember_pref');

        // Assert
        expect(value).toBe('1');
    });

    test('setCookieShort with seconds=0 expires the cookie immediately', () => {
        // Arrange
        setCookie('to_expire', 'present', 365);
        expect(getCookie('to_expire')).toBe('present');

        // Act
        setCookieShort('to_expire', '', 0);

        // Assert
        expect(getCookie('to_expire')).toBeNull();
    });

    test('getCookie tolerates "=" characters inside the cookie value', () => {
        // Arrange — URL-encoded values are common (e.g. encoded emails).
        setCookie('biometric_email', 'a%3Db%40example.com', 30);

        // Act
        const value = getCookie('biometric_email');

        // Assert
        expect(value).toBe('a%3Db%40example.com');
    });
});

describe('detectPlatform', () => {
    test.each([
        ['iPad',          'ios'],
        ['iPhone',        'ios'],
        ['iPod',          'ios'],
        ['Macintosh',     'mac'],
        ['Android',       'android'],
        ['Windows',       'windows'],
        ['SomeNewThing',  'generic'],
    ])('classifies a "%s" user agent as %s', (ua, expected) => {
        // Arrange
        const fullUa = `Mozilla/5.0 (${ua}; rv:1.0) Gecko`;

        // Act
        const platform = detectPlatform(fullUa);

        // Assert
        expect(platform).toBe(expected);
    });
});

describe('platformLabel', () => {
    const labels = {
        labelIos: 'Use Face ID / Touch ID',
        labelMac: 'Use Touch ID',
        labelAndroid: 'Use Biometric',
        labelWindows: 'Use Windows Hello',
        labelGeneric: 'Use Passkey',
    };

    test.each([
        ['iPhone',     'Use Face ID / Touch ID'],
        ['Macintosh',  'Use Touch ID'],
        ['Android',    'Use Biometric'],
        ['Windows',    'Use Windows Hello'],
        ['Linux',      'Use Passkey'],
    ])('returns the right label for a "%s" user agent', (ua, expected) => {
        // Arrange
        const fullUa = `Mozilla/5.0 (${ua})`;

        // Act
        const label = platformLabel(labels, fullUa);

        // Assert
        expect(label).toBe(expected);
    });
});

describe('decodeRegistrationOptions', () => {
    test('decodes challenge, user.id, and excludeCredentials[].id in place', () => {
        // Arrange — three known base64url payloads.
        const challenge = base64urlEncode(new Uint8Array([1, 2, 3]).buffer);
        const userId   = base64urlEncode(new Uint8Array([4, 5, 6]).buffer);
        const credId   = base64urlEncode(new Uint8Array([7, 8, 9]).buffer);
        const options  = {
            challenge,
            user: { id: userId },
            excludeCredentials: [{ id: credId, type: 'public-key' }],
        };

        // Act
        decodeRegistrationOptions(options);

        // Assert
        expect(bytes(options.challenge)).toEqual([1, 2, 3]);
        expect(bytes(options.user.id)).toEqual([4, 5, 6]);
        expect(bytes(options.excludeCredentials[0].id)).toEqual([7, 8, 9]);
        expect(options.excludeCredentials[0].type).toBe('public-key');
    });

    test('does not require excludeCredentials to be present', () => {
        // Arrange
        const options = {
            challenge: base64urlEncode(new Uint8Array([1]).buffer),
            user: { id: base64urlEncode(new Uint8Array([2]).buffer) },
        };

        // Act
        const result = decodeRegistrationOptions(options);

        // Assert
        expect(result).toBe(options);
        expect(bytes(options.challenge)).toEqual([1]);
        expect(bytes(options.user.id)).toEqual([2]);
    });
});

describe('decodeAssertionOptions', () => {
    test('decodes challenge and allowCredentials[].id in place', () => {
        // Arrange
        const challenge = base64urlEncode(new Uint8Array([10, 11]).buffer);
        const credId   = base64urlEncode(new Uint8Array([12, 13]).buffer);
        const options  = {
            challenge,
            allowCredentials: [{ id: credId, type: 'public-key', transports: ['internal'] }],
        };

        // Act
        decodeAssertionOptions(options);

        // Assert
        expect(bytes(options.challenge)).toEqual([10, 11]);
        expect(bytes(options.allowCredentials[0].id)).toEqual([12, 13]);
        expect(options.allowCredentials[0].transports).toEqual(['internal']);
    });

    test('tolerates a missing allowCredentials field', () => {
        // Arrange
        const options = { challenge: base64urlEncode(new Uint8Array([99]).buffer) };

        // Act
        decodeAssertionOptions(options);

        // Assert
        expect(bytes(options.challenge)).toEqual([99]);
    });
});

describe('encode payload helpers', () => {
    test('encodeAttestationPayload serializes every binary field as base64url', () => {
        // Arrange
        const credential = {
            id: 'cred-id',
            rawId: new Uint8Array([1, 2, 3]).buffer,
            type: 'public-key',
            response: {
                clientDataJSON:    new Uint8Array([4, 5]).buffer,
                attestationObject: new Uint8Array([6, 7]).buffer,
            },
        };

        // Act
        const payload = encodeAttestationPayload(credential);

        // Assert
        expect(payload.id).toBe('cred-id');
        expect(payload.type).toBe('public-key');
        expect(bytes(base64urlDecode(payload.rawId))).toEqual([1, 2, 3]);
        expect(bytes(base64urlDecode(payload.response.clientDataJSON))).toEqual([4, 5]);
        expect(bytes(base64urlDecode(payload.response.attestationObject))).toEqual([6, 7]);
    });

    test('encodeAssertionPayload encodes a present userHandle', () => {
        // Arrange
        const assertion = {
            id: 'assert-id',
            rawId: new Uint8Array([1]).buffer,
            type: 'public-key',
            response: {
                clientDataJSON:    new Uint8Array([2]).buffer,
                authenticatorData: new Uint8Array([3]).buffer,
                signature:         new Uint8Array([4]).buffer,
                userHandle:        new Uint8Array([5]).buffer,
            },
        };

        // Act
        const payload = encodeAssertionPayload(assertion);

        // Assert
        expect(payload.id).toBe('assert-id');
        expect(bytes(base64urlDecode(payload.response.userHandle))).toEqual([5]);
    });

    test('encodeAssertionPayload nulls out a missing userHandle', () => {
        // Arrange
        const assertion = {
            id: 'assert-id',
            rawId: new Uint8Array([1]).buffer,
            type: 'public-key',
            response: {
                clientDataJSON:    new Uint8Array([2]).buffer,
                authenticatorData: new Uint8Array([3]).buffer,
                signature:         new Uint8Array([4]).buffer,
                userHandle:        null,
            },
        };

        // Act
        const payload = encodeAssertionPayload(assertion);

        // Assert
        expect(payload.response.userHandle).toBeNull();
    });
});
