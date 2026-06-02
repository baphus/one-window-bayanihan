import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    server: {
        host: '127.0.0.1',
        port: 5173,
        cors: {
            origin: true,
            credentials: true,
            preflightContinue: true,
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
                'resources/js/chatbot-widget/index.jsx',
            ],
            refresh: true,
        }),
        react(),
    ],
});
