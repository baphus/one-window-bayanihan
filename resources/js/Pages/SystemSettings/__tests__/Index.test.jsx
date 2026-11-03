import '@testing-library/jest-dom/vitest';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('@inertiajs/react', () => ({ Head: () => null, router: { post: vi.fn() } }));
vi.mock('@/Layouts/AppLayout', () => ({ default: ({ children }) => <div>{children}</div> }));
vi.mock('@/Hooks/useUnsavedChanges', () => ({ default: () => ({ UnsavedModal: null, bypassNext: vi.fn() }) }));

globalThis.route = (name) => `/mocked/${name}`;

import SystemSettings from '../Index';

afterEach(cleanup);

describe('System settings', () => {
  it('does not render chatbot knowledge refresh controls', () => {
    render(<SystemSettings />);

    expect(screen.queryByText('Chatbot knowledge')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /refresh knowledge/i })).not.toBeInTheDocument();
  });
});
