/**
 * Browser stub for Node.js `util` module.
 *
 * `object-inspect` (used by side-channel -> qs -> @inertiajs/core) requires
 * `util.inspect` and accesses `.custom` on it. In the browser, Node.js `util`
 * is externalized by Vite as a Proxy that throws on any property access.
 *
 * This stub provides a safe replacement: the `.custom` property is a Symbol
 * for `nodejs.util.inspect.custom`, so object-inspect's code path:
 *   var utilInspect = require('./util.inspect');
 *   var inspectCustom = utilInspect.custom;
 *   var inspectSymbol = isSymbol(inspectCustom) ? inspectCustom : null;
 * works without error. `inspectSymbol` will be the Symbol, but since
 * `utilInspect` itself is undefined (it was `require('util').inspect`),
 * the actual custom inspect path is never triggered.
 */

var inspect = {
    custom: typeof Symbol !== 'undefined'
        ? Symbol.for('nodejs.util.inspect.custom')
        : null,
};

module.exports = inspect;
