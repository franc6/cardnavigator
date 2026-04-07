// Jest setup file (CommonJS so it's evaluated before any test module loads).
// jsdom does not expose TextEncoder / TextDecoder on the global object yet, so
// pull them in from Node's util module to match the browser environment our
// production code targets.
const { TextEncoder, TextDecoder } = require('node:util');

if (typeof globalThis.TextEncoder === 'undefined') {
    globalThis.TextEncoder = TextEncoder;
}
if (typeof globalThis.TextDecoder === 'undefined') {
    globalThis.TextDecoder = TextDecoder;
}
