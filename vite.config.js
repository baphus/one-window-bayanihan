import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig(({ mode }) => {
    const appUrl = loadEnv(mode, process.cwd(), '').APP_URL || 'http://localhost';
    const trustedOrigins = new Set([
        new URL(appUrl).origin,
        'http://localhost:5173',
        'http://127.0.0.1:5173',
    ]);

    return {
        resolve: {
            dedupe: ['react', 'react-dom'],
            alias: {
                /**
                 * `@/` path alias matching tsconfig.json paths.
                 * Must be absolute for rolldown (Vite 8) to resolve correctly.
                 */
                '@': path.resolve(import.meta.dirname, 'resources/js'),
            },
        },
        server: {
            host: '127.0.0.1',
            port: 5173,
            cors: {
                origin: [...trustedOrigins],
                credentials: true,
                preflightContinue: true,
            },
            hmr: {
                host: '127.0.0.1',
                protocol: 'ws',
            },
        },
        plugins: [
            /**
             * Intercept `util` / `node:util` before rolldown's built-in detection.
             *
             * Rolldown (Vite 8) detects Node.js built-in modules BEFORE applying
             * `resolve.alias`, so the old `util: stub-path` alias didn't work in
             * production builds — rolldown would still generate the throwing Proxy.
             *
             * This plugin runs at the `resolveId` stage with `enforce: 'pre'` so
             * the redirect happens before any built-in externalization logic.
             */
            {
                name: 'resolve-util-stub',
                enforce: 'pre',
                resolveId(source) {
                    if (source === 'util' || source === 'node:util') {
                        return path.resolve(import.meta.dirname, 'resources/js/vendor-stubs/util-stub.js');
                    }
                },
            },
            {
                name: 'dev-tunnel-headers',
                configureServer(server) {
                    server.middlewares.use((req, res, next) => {
                        if (trustedOrigins.has(req.headers.origin)) {
                            res.setHeader('Access-Control-Allow-Private-Network', 'true');
                            if (req.method === 'OPTIONS') {
                                res.statusCode = 204;
                                res.end();
                                return;
                            }
                        }
                        next();
                    });
                },
            },
            laravel({
                input: [
                    'resources/js/app.tsx',
                    'resources/css/app.css',
                ],
                refresh: true,
            }),
            react(),
        ],
    };
});
