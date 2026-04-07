// Babel config used solely by Jest (via babel-jest) to transform the ES-module
// frontend sources for the Node test runtime. Vite handles the production build
// natively, so this config is intentionally scoped to the test path.
module.exports = {
    presets: [
        ['@babel/preset-env', { targets: { node: 'current' } }],
    ],
};
