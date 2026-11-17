import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import viteConfig from '../../../../vite.config.js';

describe('Vite development CORS', () => {
    beforeEach(() => vi.stubEnv('APP_URL', 'https://dmw7.owbap.app'));
    afterEach(() => vi.unstubAllEnvs());

    it('allows only configured app and local Vite origins', async () => {
        const config = await viteConfig({ mode: 'test', command: 'serve' });
        const origins = config.server.cors.origin;

        expect(origins).toEqual([
            'https://dmw7.owbap.app',
            'http://localhost:5173',
            'http://127.0.0.1:5173',
        ]);
    });

    it('grants private-network access and handles preflight only for trusted origins', async () => {
        const config = await viteConfig({ mode: 'test', command: 'serve' });
        const plugin = config.plugins.find(({ name }) => name === 'dev-tunnel-headers');
        const middleware = vi.fn();
        plugin.configureServer({ middlewares: { use: (handler) => { middleware.mockImplementation(handler); } } });

        const trustedResponse = { setHeader: vi.fn(), end: vi.fn() };
        const trustedNext = vi.fn();
        middleware({ method: 'OPTIONS', headers: { origin: 'https://dmw7.owbap.app' } }, trustedResponse, trustedNext);
        expect(trustedResponse.setHeader).toHaveBeenCalledWith('Access-Control-Allow-Private-Network', 'true');
        expect(trustedResponse.statusCode).toBe(204);
        expect(trustedResponse.end).toHaveBeenCalledOnce();
        expect(trustedNext).not.toHaveBeenCalled();

        const attackerResponse = { setHeader: vi.fn(), end: vi.fn() };
        const attackerNext = vi.fn();
        middleware({ method: 'OPTIONS', headers: { origin: 'https://attacker.example' } }, attackerResponse, attackerNext);
        expect(attackerResponse.setHeader).not.toHaveBeenCalled();
        expect(attackerResponse.end).not.toHaveBeenCalled();
        expect(attackerNext).toHaveBeenCalledOnce();
    });
});
