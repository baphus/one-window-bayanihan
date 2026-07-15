/// <reference types="vitest" />
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({ input: ['resources/js/app.tsx'], refresh: true }),
        react(),
    ],
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./resources/js/test-setup.ts'],
        css: true,
        exclude: [
            '**/node_modules/**',
            '**/vendor/**',
            '.omo/**',
            // Other sessions' git worktrees — their test copies import '@/'
            // which aliases into THIS tree's resources/js, so running them
            // here tests a mixed franken-state.
            '.claude/**',
        ],
    },
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },
});
