import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    resolve: {
        dedupe: ['react', 'react-dom'],
        alias: {
            /**
             * Stub Node.js `util` module for browser build.
             * `object-inspect` (dep of side-channel -> qs -> @inertiajs/core)
             * does `require('./util.inspect')` which does `require('util').inspect`.
             * Without this alias, Vite externalizes `util` as a Proxy that throws
             * on any property access with "Module "" has been externalized".
             */
            util: path.resolve(__dirname, 'resources/js/vendor-stubs/util-stub.js'),
            /**
             * `@/` path alias matching tsconfig.json paths.
             * Must be absolute for rolldown (Vite 8) to resolve correctly.
             */
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
    server: {
        host: '127.0.0.1',
        allowedHosts: ['.lhr.life'],
        port: 5173,
        cors: {
            origin: true,
            credentials: true,
            preflightContinue: true,
        },
        hmr: {
            host: '127.0.0.1',
            protocol: 'ws',
        },
    },
    plugins: [
        {
            name: 'dev-tunnel-headers',
            configureServer(server) {
                server.middlewares.use((req, res, next) => {
                    res.setHeader('Access-Control-Allow-Private-Network', 'true');
                    if (req.method === 'OPTIONS') {
                        res.statusCode = 204;
                        res.end();
                        return;
                    }
                    next();
                });
            },
        },
        laravel({
            input: [
                'resources/js/app.tsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
